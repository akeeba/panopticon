# MCP Server

Akeeba Panopticon includes an **optional** [Model Context Protocol](https://modelcontextprotocol.io) (MCP) server. It
lets AI agents and chatbots — such as Claude Code, Claude Desktop, Codex, Cursor, VS Code, or JetBrains IDEs — drive
Panopticon using the same operations as the [JSON API](API-Overview), so you can ask an assistant things like “which of
my sites have pending updates?” or “refresh site 12 and tell me if its backup is healthy”.

> **Minimum version:** the MCP server is available in **Akeeba Panopticon 2.2.0 and later**.

The MCP server is **disabled by default**. You must enable it, and every request must authenticate with a Panopticon
API token. An AI agent can only ever see and do what the token's owner could do through the JSON API.

## Enabling the MCP server

1. Log into Panopticon as a Super User.
2. Go to **System Configuration → Security → MCP Server**.
3. Turn on **Enable the MCP server** and save.

You can also enable it from the command line or by setting the `mcp_enabled` configuration option to `true` (see
[Configuration parameters](Configuration-parameters)).

While the server is disabled the endpoint responds as if it does not exist (HTTP 404).

## Endpoint URL

The universal form, which works on any server with or without URL rewriting:

```
https://panopticon.example.com/index.php/mcp
```

The short form, which requires a rewrite rule (the shipped `.htaccess` provides it as of Panopticon 2.2.1; without it,
`/mcp` is a plain filesystem 404):

```
https://panopticon.example.com/mcp
```

Both forms route to the same server. The MCP server implements the **Streamable HTTP** transport in **stateless** mode:
each request is self-contained and authenticated by a static HTTP header, so there is no session handshake to manage.

## Authentication

Every request must carry a Panopticon API token in a standard HTTP **Bearer Authorization** header:

```
Authorization: Bearer YOUR_API_TOKEN
```

To mint a token, log into Panopticon, click your name (top-right) → **API Tokens**, and create one. The token inherits
your account's permissions. You can also restrict a token to specific **scopes** (e.g. read-only) — the MCP server
honours those scopes exactly like the JSON API does. See [API Overview](API-Overview) for details on tokens and scopes.

> **Security tip:** treat an API token like a password. Anyone holding it can use the MCP server with your account's
> permissions. Prefer a dedicated, least-privilege token (restricted scopes, and an account that only has access to the
> sites you want the assistant to manage).

> **Header-stripping gotcha.** Many web server / PHP combinations strip the `Authorization` header before it reaches
> PHP, causing every request to fail with a 401 even when the token is correct. The shipped `.htaccess` handles this on
> Apache; Nginx and IIS need a little configuration. If the header is dropped *before PHP starts* (common on shared
> hosting running `cgi-fcgi`/LSPHP with restricted `AllowOverride`) and no configuration recovers it, use the
> `X-Panopticon-Token` header described next.

### Alternative: the `X-Panopticon-Token` header

If the `Authorization` header does not reach PHP on your host, send the token in the `X-Panopticon-Token` header
instead — Panopticon accepts it identically, and being a custom header it survives most `cgi-fcgi`/LSPHP setups:

```
X-Panopticon-Token: YOUR_API_TOKEN
```

For stdio-only clients, or clients that do not let you set a raw header, bridge with
[supergateway](https://github.com/supercorp-ai/supergateway):

```json
{
  "mcpServers": {
    "panopticon-yoursite": {
      "command": "npx",
      "args": [
        "-y", "supergateway",
        "--streamableHttp", "https://panopticon.example.com/index.php/mcp",
        "--header", "X-Panopticon-Token: YOUR_API_TOKEN"
      ]
    }
  }
}
```

## Security model

The MCP server is designed so that an AI agent can never exceed the boundaries of the token it uses:

- **Same permissions as the API.** Every tool enforces the same access control as the equivalent API endpoint. A user
  who can only access sites A and B will not even see that sites C and D exist when listing sites.
- **Token scopes apply.** A tool is only offered if the token grants the scope that the matching API endpoint requires
  (for example, the `schedule_cms_update` tool needs the `sites:cms-update` scope). A token with no explicit scopes is
  treated as granting all scopes, exactly as in the API.
- **Secrets are not exposed.** Unlike the raw JSON API, MCP tools deliberately omit stored secrets (such as extension
  download keys and connection credentials) from their output, so they are never fed into an AI model's context.

### Controlling which tools are available

There are two layers of control over the set of tools the MCP server offers, on top of the per-user permissions above.

**1. Globally disabled tools (the kill-switch).** In **System Configuration → Security → MCP Server**, the *Globally
disabled tools* field accepts a comma-separated list of tool names that are **never** exposed to anyone, regardless of
user, token, or group. For example, add `get_sysconfig` to keep system configuration entirely out of MCP. This list
always wins.

**2. Per-user-group disabled tools.** When editing a [user group](Users-and-Groups), the *Disabled MCP tools* field
lets a Super User deny specific tools to members of that group.

> ### Important: ALLOW wins for per-group restrictions
>
> When a user belongs to **several** groups, a tool is denied **only if every one of their groups disables it**. Being
> granted a tool by *any* group overrides being denied it by another group.
>
> This is the **opposite** of Joomla's “deny wins” access control. It is a deliberate *convention over configuration*
> choice: tools stay available unless they are *universally* restricted. If you need an absolute block, use the global
> *Globally disabled tools* kill-switch described above, which always takes precedence.

## Available tools

| Tool | What it does | Required scope | Super User only |
|------|--------------|----------------|-----------------|
| `list_sites` | List the sites you can access (with search/filter) | `sites:read` | no |
| `get_site` | Get a single site's details | `sites:read` | no |
| `get_site_status` | Health summary for a site (CMS/PHP/extensions/backup) | `sites:read` | no |
| `list_site_extensions` | List a site's extensions/plugins and update state | `sites:extensions` | no |
| `list_tasks` | List scheduled tasks (others must pass a `site_id` they administer) | `tasks:read` | no |
| `get_task` | Get a single scheduled task | `tasks:read` | no |
| `get_stats` | Global dashboard counters across all sites/tasks | `sites:read` | yes |
| `get_sysconfig` | Read non-sensitive system configuration | `sysconfig:read` | yes |
| `get_selfupdate_info` | Whether a Panopticon update is available | `selfupdate:read` | yes |
| `refresh_site` | Refresh a site's information now | `sites:refresh` | no |
| `schedule_cms_update` | Schedule a CMS core update for a site | `sites:cms-update` | no |
| `cancel_cms_update` | Cancel a scheduled CMS update | `sites:cms-update` | no |
| `schedule_extension_update` | Schedule an extension/plugin update | `sites:extensions` | no |
| `cancel_extension_update` | Cancel a queued extension/plugin update | `sites:extensions` | no |

## Setting up the MCP server in AI tools

All of the coding tools below support Streamable HTTP MCP servers with a static HTTP header. **The configuration key for
the server URL is `url` for every tool except Antigravity (which uses `serverUrl`) and Qwen Code (which uses `httpUrl`).**

In every example, replace `https://panopticon.example.com/mcp` with your endpoint and `YOUR_API_TOKEN` with your token.

### Claude Code

Use the CLI to add the server:

```bash
claude mcp add --transport http panopticon https://panopticon.example.com/mcp \
  --header "Authorization: Bearer YOUR_API_TOKEN"
```

### Claude Desktop

Open **Settings → Connectors → Add custom connector**, give it a name (e.g. *Panopticon*), and set the URL to your
endpoint. If your build of Claude Desktop lets you add request headers, add `Authorization: Bearer YOUR_API_TOKEN`.
Otherwise, append the token as a query parameter instead (`…/index.php/mcp?_panopticon_token=YOUR_API_TOKEN`), which
Panopticon also accepts. **URL-encode the token** when passing it this way: API tokens are Base64 and contain characters
such as `+` that a URL otherwise mangles (an unencoded `+` decodes to a space and corrupts the token).

### Codex CLI

Edit `~/.codex/config.toml`:

```toml
[mcp_servers.panopticon]
url = "https://panopticon.example.com/mcp"

[mcp_servers.panopticon.http_headers]
Authorization = "Bearer YOUR_API_TOKEN"
```

### Codex Desktop

In Codex Desktop's MCP settings, add a new server of type *Streamable HTTP* (or *Remote*), set the URL to your endpoint,
and add an `Authorization: Bearer YOUR_API_TOKEN` header.

### Qwen Code

Qwen Code uses the key **`httpUrl`** (not `url`). Edit `~/.qwen/settings.json`:

```json
{
  "mcpServers": {
    "panopticon": {
      "httpUrl": "https://panopticon.example.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_TOKEN"
      }
    }
  }
}
```

### Mistral Vibe (formerly Le Chat)

In Mistral's tool/connector settings, add a custom MCP connector pointing at your endpoint URL and add an
`Authorization: Bearer YOUR_API_TOKEN` header.

### OpenHands

Add the server to your OpenHands MCP configuration:

```json
{
  "mcpServers": {
    "panopticon": {
      "url": "https://panopticon.example.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_TOKEN"
      }
    }
  }
}
```

### Antigravity

Antigravity uses the key **`serverUrl`** (not `url`):

```json
{
  "mcpServers": {
    "panopticon": {
      "serverUrl": "https://panopticon.example.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_TOKEN"
      }
    }
  }
}
```

### VS Code

Create `.mcp.json` in your workspace (or use **MCP: Add Server** from the Command Palette):

```json
{
  "servers": {
    "panopticon": {
      "type": "http",
      "url": "https://panopticon.example.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_TOKEN"
      }
    }
  }
}
```

### Cursor

Edit `~/.cursor/mcp.json` (global) or `.cursor/mcp.json` (per-project):

```json
{
  "mcpServers": {
    "panopticon": {
      "url": "https://panopticon.example.com/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_API_TOKEN"
      }
    }
  }
}
```

### PhpStorm (and other JetBrains IDEs)

In **Settings → Tools → AI Assistant → Model Context Protocol (MCP)**, add a server. Choose the *HTTP* / *SSE* type, set
the URL to your endpoint, and add an `Authorization: Bearer YOUR_API_TOKEN` header. (You can also paste the JSON form
with a `url` key and a `headers` object.)

### A note on ChatGPT

The consumer **ChatGPT** app does **not** support adding a custom Streamable HTTP MCP server like this one. If you want
to use Panopticon with OpenAI's tooling, install **Codex Desktop** (or the Codex CLI) on your computer and configure it
as shown above.

## Troubleshooting

- **404 responses:** the MCP server is disabled. Enable it in System Configuration.
- **401 responses:** the `Authorization: Bearer …` header is missing or the token is invalid, disabled, or expired. If
  the header is being stripped before it reaches PHP (common on `cgi-fcgi`/LSPHP shared hosting), send the token in the
  `X-Panopticon-Token` header instead. The audit log records a *reason* per failed attempt: `missing_token` = no token
  reached PHP (header stripping); `no_secret` = `config.php` has no persisted `secret` (fixed in 2.2.1 — upgrade and
  re-mint your tokens).
- **A tool you expected is missing from the list:** check (1) the token's scopes, (2) whether the tool is in the
  *Globally disabled tools* list, (3) whether every one of your groups disables it, and (4) for Super-User-only tools,
  whether your account is a Super User.
- **`GET` requests return 405:** that is expected. The stateless server only accepts `POST` for JSON-RPC messages.

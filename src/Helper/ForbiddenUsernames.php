<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Helper;

defined('AKEEBA') || die;

use Awf\Container\Container;

class ForbiddenUsernames
{
	/**
	 * Hardcoded list of forbidden usernames.
	 *
	 * Derived from Admin Tools for Joomla.
	 */
	private const FORBIDDEN = [
		".json", ".rss", ".xml", "0", "100", "101", "102", "1xx", "200", "201", "202", "203", "204", "205", "206",
		"207", "226", "2xx", "300", "301", "302", "303", "304", "305", "307", "308", "3xx", "400", "401", "402",
		"403", "404", "405", "406", "407", "408", "409", "410", "411", "412", "413", "414", "415", "416", "417",
		"418", "422", "423", "424", "426", "428", "429", "431", "451", "4xx", "500", "501", "502", "503", "504",
		"505", "506", "507", "511", "5xx", "7xx", "about", "abuse", "ac", "access", "account", "accounts",
		"activate", "activities", "activity", "ad", "add", "address", "adm", "admin", "administracion",
		"administration", "administrator", "administrators", "admintools", "ads", "adult", "advertising", "advisor",
		"ae", "af", "affiliate", "affiliates", "ag", "ai", "ajax", "akeeba", "al", "album", "albums", "alice",
		"all", "alpha", "am", "an", "analysis", "analytics", "android", "anon", "anonymous", "antichrist",
		"antipope", "ao", "api", "app", "apple", "apps", "aq", "ar", "arabic", "archive", "archived", "archives",
		"arse", "arsehole", "arsewipe", "article", "as", "asct", "asset", "asshat", "asshole", "at", "atom",
		"attrib", "attribute", "attribution", "au", "auth", "authentication", "authenticator", "author", "authors",
		"auto", "autoconfig", "automatic", "available", "avatar", "aw", "awadhi", "ax", "az", "azerbaijani", "ba",
		"backup", "backups", "bad", "baidu", "balancer-manager", "bank", "banner", "banners", "bb", "bd", "be",
		"bengali", "best", "beta", "bf", "bg", "bh", "bhojpuri", "bi", "billing", "bin", "bj", "blackberry",
		"blob", "blobs", "blog", "blogs", "bm", "bn", "bo", "board", "bob", "book", "bookmark", "bookmarked",
		"bookmarks", "bot", "bots", "bottom", "br", "broadcasthost", "browse", "bs", "bt", "bug", "bugs", "build",
		"builds", "built", "burmese", "busdev", "business", "butthole", "bv", "bw", "by", "bz", "ca", "cache",
		"cadastro", "calendar", "call", "callback", "camera", "campaign", "cancel", "captcha", "career", "careers",
		"cart", "categories", "category", "cc", "cd", "cdn", "center", "ceo", "cf", "cfo", "cg", "cgi", "cgi-bin",
		"ch", "changelog", "charlie", "chat", "check", "checking", "checkout", "checkpoint", "chef", "chinese",
		"christ", "ci", "cialis", "ciso", "ck", "cl", "classic", "client", "cliente", "clients", "cloud", "cm",
		"cn", "cname", "co", "co-op", "code", "codereview", "codes", "comercial", "comment", "comments",
		"commercial", "communities", "community", "company", "compare", "compete", "component", "components",
		"compras", "config", "configuration", "configure", "connect", "consultant", "contact", "contact-us",
		"contact_us", "contacto", "contacts", "contactus", "contest", "contractor", "contribute", "contribution",
		"contributor", "control", "convert", "cookie", "cop", "copper", "coppers", "cops", "copied", "copy",
		"copyedit", "copy-editor", "copy_editor", "copyeditor", "copyright", "corp", "corporate", "corporation",
		"coupon", "coupons", "cr", "cracker", "crash", "create", "crew", "crossdomain", "crossdomain.xml", "crypt",
		"costumer", "costumers", "cs", "cso", "css", "cto", "cu", "cum", "cunt", "customer", "customers",
		"customercare", "customerservice", "cv", "cx", "cy", "cz", "daemon", "dash", "dashboard", "data",
		"database", "db", "dba", "dbadmin", "db.admin", "db-admin", "db_admin", "dbo", "dd", "de", "default",
		"delete", "delete-me", "delete.me", "delete_me", "deleted", "deleteme", "demo", "demon", "demos", "denied",
		"deny", "deploy", "deployed", "deploys", "design", "designer", "destroy", "dev", "devel", "devil",
		"developer", "developers", "development", "deus", "diagram", "diary", "dickhead", "dict", "dictionary",
		"die", "digsitesvalue", "dir", "direct_messages", "director", "directors", "directory", "dist", "dj", "dk",
		"dm", "dmca", "dns", "do", "doc", "docimport", "docker", "docs", "document", "documentation", "documents",
		"domain", "douche", "douchebag", "douchecanoe", "download", "downloaded", "downloads", "drone", "drupal",
		"dumbass", "dutch", "dvd", "dz", "ec", "ecommerce", "edit", "edited", "editor", "editorial", "editors",
		"edits", "edu", "education", "educator", "educators", "ee", "eg", "eh", "email", "emails", "embed",
		"employment", "empty", "end", "engage", "english", "enquire", "enquiries", "enquiry", "enterprise",
		"entries", "entry", "er", "erase", "erased", "error", "errors", "es", "et", "etc", "eu", "eula", "eval",
		"event", "events", "everyone", "example", "examples", "excel", "exec", "executive", "executives", "exit",
		"expert", "experts", "expire", "expired", "explore", "export", "exported", "exports", "facebook", "fag",
		"faggot", "faq", "farsi", "favorite", "favorites", "favourite", "favourites", "fbi", "fbl", "feature",
		"features", "feed", "feedback", "feeds", "fetch", "fi", "file", "files", "finance", "find", "firewall",
		"first", "fizz", "fj", "fk", "flag", "flagged", "flags", "flash", "fleet", "fleets", "flog", "fm", "fo",
		"follow", "followers", "following", "font", "fonts", "forbidden", "forgot", "forgot-password",
		"forgot_password", "forgotpassword", "form", "forum", "forums", "founder", "founders", "fr", "free",
		"french", "friend", "friends", "ftp", "ftps", "fuck", "ga", "gadget", "gadgets", "game", "games", "gan",
		"gb", "gd", "ge", "geek", "german", "get", "gf", "gg", "gh", "ghost", "gi", "gif", "gifs", "gift",
		"gifts", "gist", "git", "github", "gl", "global", "gm", "gn", "god", "good", "google", "gp", "gq", "gr",
		"graph", "group", "groups", "gs", "gt", "gu", "guest", "guests", "guide", "guidelines", "gujarati", "gw",
		"gy", "hacked", "hacker", "hakka", "hash", "hausa", "head", "head-office", "head.office", "head_office",
		"headoffice", "headquarters", "headteacher", "hell", "helldesk", "hell.desk", "hell-desk", "hell_desk",
		"help", "help-me", "help.me", "help_me", "helpcenter", "helpdesk", "help.desk", "help-desk", "help_desk",
		"helpme", "hidden", "hide", "hindi", "history", "hk", "hm", "hn", "home", "homepage", "hooks", "host",
		"hosting", "hostmaster", "hostname", "howto", "hpg", "hq", "hr", "ht", "html", "http", "http2", "httpd",
		"https", "hu", "human", "human-resources", "human.resources", "human_resources", "humans", "i", "iamges",
		"icon", "icons", "id", "idea", "ideas", "idiot", "idiots", "ids", "ie", "il", "im", "image", "images",
		"imap", "img", "imgs", "import", "imported", "imports", "imulus", "in", "inbox", "index", "indice", "info",
		"information", "informativo", "inquiry", "instagram", "integrator", "internal", "intranet", "intro",
		"investor", "investorrelations", "investors", "invitations", "invite", "invites", "io", "ios", "ip", "ipad",
		"iphone", "ipod", "iq", "ir", "irc", "is", "isatap", "issue", "issues", "it", "italian", "item", "items",
		"japanese", "java", "javanese", "javascript", "je", "jesus", "jetbrains", "jinyu", "jm", "jo", "job",
		"jobs", "join", "joomla", "jp", "js", "json", "jump", "kannada", "ke", "key", "keynote", "keys",
		"keyserver", "kg", "kh", "ki", "kike", "km", "kn", "knowledge-base", "knowledge.base", "knowledge_base",
		"knowledgebase", "korean", "kp", "kr", "kubuntu", "kw", "ky", "kz", "l33t", "la", "language", "languages",
		"last", "lb", "lc", "ldap-status", "left", "legacy", "legal", "li", "lib", "license", "like", "liked",
		"likes", "link", "linked", "links", "linux", "list", "lists", "lk", "local", "localdomain", "localhost",
		"lock", "locked", "log", "log-in", "log-out", "log_in", "log_out", "logged", "login", "loginguard",
		"logmein", "logout", "logs", "lot", "lr", "ls", "lt", "lu", "luser", "lv", "ly", "m", "ma", "mac",
		"macos", "macosx", "magazine", "mail", "mail1", "mail2", "mail3", "mail4", "mail5", "mailer",
		"mailer-daemon", "mailing", "main", "maintenance", "maithili", "majordomo", "malayalam", "malfoy",
		"malory", "malroy", "manage", "manager", "managers", "mandarin", "manifesto", "manual", "map", "maps",
		"marathi", "market", "marketer", "marketers", "marketing", "marketplace", "markets", "master", "mastodon",
		"mc", "md", "me", "media", "member", "members", "memory", "message", "messages", "messenger", "mg", "mh",
		"microblog", "microblogs", "migrate", "migrator", "min-nan", "mine", "mis", "miss", "mister", "mk", "ml",
		"mm", "mn", "mo", "mob", "mobile", "module", "modules", "moron", "morons", "movie", "movies", "mp", "mp3",
		"mq", "mr", "mrs", "ms", "msg", "msn", "mt", "mu", "music", "musicas", "must", "mv", "mw", "mx", "my",
		"myself", "mysql", "mz", "na", "name", "named", "names", "namespace", "namespaces", "nan", "navi",
		"navigation", "nc", "ne", "nerd", "net", "network", "new", "newest", "news", "newsletter", "next", "nf",
		"ng", "ni", "nick", "nickname", "nl", "no", "no-reply", "no_reply", "nobody", "noc", "noreply", "notary",
		"notaries", "notation", "notations", "notes", "noticias", "notification", "notifications", "notified",
		"notify", "np", "nr", "ns", "ns1", "ns10", "ns2", "ns3", "ns4", "ns5", "ns6", "ns7", "ns8", "ns9", "nu",
		"null", "numbers", "nz", "oauth", "oauth_clients", "oembed", "offer", "offers", "office", "officeadmin",
		"official", "old", "oldest", "om", "on-line", "oneself", "online", "open", "opened", "openid",
		"opensourcematters", "operations", "operator", "operators", "ops", "order", "orders", "organisation",
		"organisations", "organization", "organizations", "orgs", "oriya", "os", "osm", "osx", "overview", "owner",
		"owners", "pa", "package", "paddle", "page", "pager", "pages", "paid", "panel", "panjabi", "passwd",
		"password", "passwords", "patch", "pay", "payment", "payments", "pdf", "pdfs", "pe", "pendejo", "people",
		"perl", "person", "personal", "pf", "pfy", "pg", "pgp", "ph", "phone", "photo", "photoalbum", "photos",
		"php", "phpmyadmin", "phppgadmin", "phpredisadmin", "phpstorm", "pic", "pics", "ping", "pk", "pl", "plan",
		"plans", "play", "plod", "plug-in", "plug-ins", "plugin", "plugins", "pm", "pn", "police", "policies",
		"policy", "polish", "pop", "pop3", "pope", "popular", "portal", "portuguese", "post", "postbox", "postfix",
		"postmaster", "posts", "powerpoint", "pr", "premium", "present", "presentation", "presentations",
		"president", "presos", "press", "price", "pricing", "prime", "principal", "privacy", "privacy-policy",
		"privacy_policy", "privacypolicy", "private", "private-message", "private.message", "private_message",
		"privatemessage", "proc", "prod", "product", "production", "products", "profile", "profiles", "project",
		"projects", "promo", "ps", "pt", "pub", "public", "publish", "publisher", "publishers", "purpose", "push",
		"put", "putain", "pw", "py", "python", "qa", "query", "queue", "quota", "random", "ranking", "rc", "re",
		"read", "readme", "read.me", "read-me", "read_me", "recent", "reception", "recipe", "recipes", "recover",
		"recovery", "recruit", "recruiter", "recruiters", "recruiting", "recruitment", "reddit", "refund", "refunds",
		"regional", "register", "registered", "registration", "release", "releases", "remember", "remote", "remove",
		"removed", "replies", "reply", "report", "reported", "reports", "repositories", "repository", "req",
		"request", "requests", "res", "reserved", "reset", "reset-password", "reset_password", "resetpassword",
		"resource", "resources", "restore", "restored", "result", "results", "retard", "retarded", "return",
		"returns", "revert", "review", "reviewed", "reviews", "right", "ro", "robot", "robots", "robots.txt",
		"roc", "romanian", "root", "rs", "rss", "ru", "ruby", "rule", "rules", "russian", "rw", "sa", "sag",
		"sale", "sales", "sample", "samples", "satan", "save", "saved", "sb", "sc", "scan", "scanning", "school",
		"school-office", "school.office", "school_office", "schooloffice", "script", "scripts", "sd", "se",
		"search", "searched", "secret", "secretary", "secrets", "secure", "security", "self", "send", "sent",
		"serbo-croatian", "serve", "server", "server-info", "server-status", "servers", "service", "services",
		"session", "sessions", "setting", "settings", "setup", "sftp", "sg", "sh", "share", "shared", "shares",
		"sharing", "shitgibbon", "shop", "shopping", "show", "si", "sign-in", "sign-up", "sign_in", "sign_up",
		"signin", "signout", "signup", "simon", "sindhi", "site", "sitemap", "sitemap.xml", "sites", "sj", "sk",
		"sl", "sm", "smartphone", "smb", "smtp", "sn", "so", "sociallogin", "sony", "soporte", "source", "sources",
		"spam", "spanish", "spastic", "spaz", "spec", "special", "specimen", "specimens", "specs", "sql",
		"sqladmin", "sql.admin", "sql-admin", "sql_admin", "sr", "src", "ss", "ssh", "ssl", "ssl-admin",
		"ssladmin", "ssladministrator", "sslwebmaster", "st", "stable", "stacks", "staff", "stage", "staging",
		"star", "starred", "stars", "start", "stat", "state", "static", "statistics", "stats", "status", "statuses",
		"storage", "store", "stores", "stories", "style", "styleguide", "styles", "stylesheet", "stylesheets", "su",
		"subcontractor", "subdomain", "subscribe", "subscribed", "subscriber", "subscribers", "subscription",
		"subscriptions", "sunda", "suporte", "support", "support-details", "supportdetails", "surprise", "survey",
		"sv", "svn", "swf", "sy", "sys", "sysadmin", "sysadministrator", "sysop", "system", "sz", "tablet",
		"tablets", "tag", "tags", "talk", "tamil", "task", "tasks", "tc", "td", "team", "teams", "tech",
		"technologies", "technology", "telnet", "telugu", "template", "term", "terms", "terms-of-service",
		"terms-of-use", "terms_of_service", "termsofservice", "test", "test1", "test2", "test3", "teste", "testing",
		"tests", "tf", "tg", "th", "thai", "theme", "themes", "theoffice", "thread", "threads", "timeline",
		"tinder", "tj", "tk", "tl", "tls", "tm", "tmp", "tn", "to", "todo", "token", "tokens", "tokenserver",
		"tool", "tools", "top", "topic", "topics", "tos", "tour", "tp", "tr", "translation", "translations",
		"translator", "translators", "trash", "trending", "trends", "trial", "trials", "trust", "tt", "turkish",
		"tutorial", "tux", "tv", "tw", "twitter", "twittr", "txt", "tz", "ua", "ubuntu", "ug", "uk", "ukrainian",
		"unavailable", "undef", "unfollow", "university", "unread", "unsubscribe", "unsupported", "update",
		"updated", "upgrade", "upload", "uploaded", "uploads", "uptime", "urdu", "url", "us", "usage", "usenet",
		"user", "username", "users", "usr", "usuario", "util", "uucp", "uy", "uz", "va", "vault", "vc", "ve",
		"vendas", "vendor", "ver", "version", "vg", "vi", "viagra", "vicepresident", "vice.president", "video",
		"videos", "vietnamese", "view", "views", "visitor", "vn", "vp", "vpn", "vu", "wallet", "warranty", "watch",
		"weather", "web", "webhook", "webhooks", "weblog", "webmail", "webmaster", "webmasters", "website",
		"websites", "welcome", "wf", "widget", "widgets", "wiki", "win", "windows", "wishlist", "word", "wordpress",
		"work", "workplace", "works", "workshop", "wpad", "ws", "wu", "ww", "wws", "www", "www1", "www2", "www3",
		"www4", "www5", "www6", "www7", "wwws", "wwww", "xfn", "xiang", "xml", "xmpp", "xpg", "xss", "xubuntu",
		"xx", "xxx", "yahoo", "yaml", "yandex", "ye", "year", "yelp", "yes", "yml", "yoruba", "you", "yourdomain",
		"yourname", "yourself", "yoursite", "yourusername", "yt", "yu", "za", "zip", "zm", "zuck", "zw",
	];

	/**
	 * Check if a username is forbidden.
	 *
	 * @param   string     $username   The username to check
	 * @param   Container  $container  The application container
	 *
	 * @return  bool  True if the username is forbidden
	 */
	public static function isForbidden(string $username, Container $container): bool
	{
		$username = strtolower(trim($username));

		if (empty($username))
		{
			return true;
		}

		// Check the hardcoded list if blocking is enabled
		if ($container->appConfig->get('user_registration_block_usernames', true))
		{
			if (in_array($username, self::FORBIDDEN, true))
			{
				return true;
			}
		}

		// Check the custom blocked usernames
		$customBlocked = $container->appConfig->get('user_registration_custom_blocked_usernames', '');

		if (!empty($customBlocked))
		{
			$customList = array_map(
				fn($line) => strtolower(trim($line)),
				preg_split('/[\s,]+/', (string) $customBlocked, -1, PREG_SPLIT_NO_EMPTY)
			);

			if (in_array($username, $customList, true))
			{
				return true;
			}
		}

		return false;
	}
}

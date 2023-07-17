# Security Policy

## Supported Versions

Only the latest released version and the `development` branch are supported with security updates.

We only support the use of our software in [currently maintained PHP versions](https://www.php.net/supported-versions) meeting the minimum PHP version requirements expressed in the software's `compose.json` file. Use with unsupported (End of Life) versions of PHP is not supported. 

## Reporting a Vulnerability

Please **DO NOT** file a GitHub issue about security issues. GitHub issues are public.
Filing an issue about a security issue puts all users, you included, in immediate danger.

Please use our [contact page](https://www.akeebabackup.com/contact-us.html) to send us a
private notification about the security issue. We strongly recommend using GPG to encrypt
your email. You can find the lead developer's public GPG key at https://keybase.io/nikosdion

Please include instructions to reproduce the security issue. Better yet, please include Proof
Of Concept code if applicable.

## Expected timeframe

We will acknowledge your security report within 10 working days. If you do not receive feedback please make sure that your report is complete and contact us again.

We will address the security issue within 30 calendar days and publish a security release with a security notice on our site's News section, also linked to from our home page. During that time we kindly request that you do not publicly share any information about the security issue to give us enough time to address it properly, without putting anyone in immediate danger.

We kindly request that you refrain from disclosing details of the security issue for a further 30 calendar days from the date we make a security release. This gives users a reasonable amount time to update. After that embargo period you may publicly share information about the security issue, including Proof of Concept code.

## Dos and don'ts

Please do:
* use encrypted email. While unlikely anyone would be eavesdropping, let's not risk it.
* state your credentials in security research, if applicable. It helps us communicate with each other more clearly.
* include reproduction information. Security issues which cannot be reproduced cannot be addressed.
* include your reasoning on why this behaviour is a security issue.

We will appreciate it if you do:
* include an executive summary of the security issue, no more than 100 words. This helps a lot!
* include Proof of Concept code, if applicable / possible. We will greatly appreciate it!
* tell us if you want attribution when we fix the security issue, and under which name or alias. We will oblige to your preference.

Please do not:
* report security issues in public. It is dangerous, and reflects badly on you.
* report issues which fall under the category of “I can hack myself” or “If I give elevated permissions to someone they can hack me”. This is obviously the expected behaviour.
* report issues which require the site operator to make an insecure choice, modify the code we ship, install new code, or are not directly related to how our software works (e.g. security issues in the server environment). These are outside our control.
* copy and paste a report from a site scanner tool without a reproducible, verifiable vulnerability. These tools report _a lot_ of false positives and require a human to sift through them. We are NOT going to do that for you.
* ask for a bug bounty. We get it, we also want to be paid for doing work. Unfortunately, we are a tiny company of two people; we don't have the budget for a bug bounty, and the tax code says we can't disburse any money without an invoice anyway.
* demand payment to disclose an alleged security issue, or details of an alleged security issue. This reads as a scam, or extortion. It's also the quickest way to get permanently blacklisted in the security community.

Thank you for your understanding, and we're looking forward to working with you to make everyone's installation of our software more secure!
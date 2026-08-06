# Project Unveiled — Codex Instructions

## Mission

Maintain and grow bobsome1.com as the public home of Project Unveiled by Robert J. Hayes. The work should invite honest questions, distinguish evidence from interpretation, create mystery and hope without manipulation, and direct public traffic to https://bobsome1.com.

## Working Style

- Lead with completed outcomes and concrete next actions.
- Begin when the goal is clear. Ask only when a missing decision would materially change the result.
- Make reasonable assumptions explicit.
- Preserve Robert's direct, conversational voice. Avoid generic religious advertising, corporate filler, exaggerated promises, and obvious AI phrasing.
- Separate verified fact, inference, opinion, theological interpretation, and disputed claims.
- Challenge weak ideas respectfully and explain tradeoffs in plain language.
- Prefer the fastest safe, low-cost path that preserves ownership and can run on limited hardware.

## Project Boundaries

- Public product: Project Unveiled book, reader, evidence files, timeline, research standards, corrections, email signup, outreach, and support pages.
- Private products such as Watch-Dawg AI and Ozark Atlas must remain separate unless a task explicitly includes them.
- Do not rewrite manuscript theology or historical claims incidentally during technical work.
- Do not weaken the core posture: Jesus is not being removed; truth is not afraid of questions; evidence must remain visible.

## Technical Environment

- Production hosting is Namecheap shared hosting using Apache, cPanel, static HTML/CSS/JavaScript, and PHP.
- Preserve compatibility with shared hosting. Do not require Node, Docker, a persistent application server, or paid infrastructure in production without explicit approval.
- Use absolute public paths consistently and keep https://bobsome1.com as the canonical origin.
- Keep mobile performance, accessibility, search indexing, and simple cPanel deployment first-class.
- Prefer open-source, low-cost, and fully owned solutions.

## Security and Privacy

- Never commit or expose passwords, access tokens, SSH keys, database credentials, subscriber records, analytics records, IP data, server logs, private configuration, SQL exports, or hosting backups.
- Private runtime data belongs outside public_html and outside Git.
- Preserve authentication on /owner/ and all private tools.
- Keep forms protected against spam, unsafe input, cross-origin submission, header injection, and accidental data exposure.
- Preserve the promise that reader information is not sold, rented, or traded.
- Do not commit ZIP archives, dated backup pages, installer scripts, or error logs.

## Change Rules

- Inspect existing behavior before editing.
- Preserve unrelated work and manuscript content.
- Make focused changes with a clear rollback path.
- Do not deploy directly to production unless Robert explicitly requests deployment.
- Use a branch and draft pull request for substantial changes when the repository supports it.
- Describe what changed, why, user impact, validation performed, and any remaining risk.

## Required Verification

For every relevant change:

1. Check internal links, local assets, fragments, duplicate IDs, page titles, H1 counts, and JSON-LD.
2. Validate changed JavaScript syntax.
3. Validate changed PHP syntax when PHP tooling is available.
4. Test mobile-width layout and keyboard accessibility when presentation changes.
5. Confirm the homepage, reader, Chapter 1, timeline, research pages, signup flow, privacy page, and protected owner route still behave correctly.
6. Confirm no secrets, logs, backups, subscriber data, or private runtime files entered the diff.

A task is complete only when the requested behavior works, relevant checks pass, and deployment or rollback instructions are clear.

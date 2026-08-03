# Making Claude faster on this project — MCP & Skills

A short, practical setup. Do the first one; the rest are optional.

## 1. Laravel Boost (do this — biggest win)

Boost is an official Laravel MCP server. It gives Claude tools to read your
**actual** routes, DB schema, config, and models, run Tinker, read logs, and
search docs for **your installed versions** (Laravel 13, Inertia 2, etc.)
instead of guessing. This is the single highest-value add for a Laravel repo.

```bash
composer require laravel/boost --dev
php artisan boost:install      # registers the MCP server + writes guidelines
```

`boost:install` wires the MCP config for you and asks which AI tools to set up
(pick Claude Code). After it finishes, restart Claude Code so it picks up the
new server. You'll know it worked when Claude can answer "what routes exist?"
or "show the users table schema" without grepping.

Why it matters here specifically:
- Reads the **real** migration state, so it won't suggest a column that already
  exists or miss the `/setup` round-trip.
- Version-accurate docs — no stale Laravel 10 advice on a Laravel 13 app.
- Tinker access = it can check a balance or a signatory live instead of writing
  a throwaway script.

## 2. Project-scoped MCP config (`.mcp.json`)

Boost writes its own entry, but if you add more MCP servers, keep them in a
**project-scoped** `.mcp.json` at the repo root so they're shared and
version-controlled. Shape:

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    }
  }
}
```

Add servers with `claude mcp add <name> -- <command>` (writes to the config),
and list them with `claude mcp list`. Prefer **project scope** for anything the
whole team should have; use **user scope** for personal tools (e.g. a GitHub
token-based server you don't want committed).

Other MCP servers worth considering *only if you actually need them*:
- A **filesystem** server if you frequently work with files outside the repo.
- A **GitHub** server (PRs/issues) — but the `gh` CLI already covers most of
  this without an MCP.
Don't over-install: every MCP server adds tools Claude has to consider, which
can slow it down. Boost alone covers ~90% of the value here.

## 3. Skills (already available — know which to reach for)

Skills are task playbooks Claude loads on demand. For this project the useful
ones are mostly for **deliverables built from the app's data**:

- **xlsx** — generate the CSC leave ledger (the Montemayor/Obviar-style cards)
  as a real Excel file. Trigger: "export the leave ledger as xlsx".
- **pdf** — combine/split/stamp PDFs, or turn a printed CS Form 6 into a PDF
  deliverable.
- **docx** — office memos/letters if you ever need them as Word files.
- **artifact-design** / **dataviz** — if you build a dashboard or chart.

You don't install these per-project; they load automatically when a task
matches. Just phrase the request in those terms ("as an xlsx", "as a PDF").

If you want a **repo-specific skill** (e.g. "deploy CivDir" that encodes the
pull → set token → `/setup` → blank token flow), create one under
`.claude/skills/<name>/SKILL.md`. That turns the deploy playbook in `CLAUDE.md`
into a one-word command.

## 4. Settings that speed things up

- Add common read-only commands to the project allowlist so Claude isn't
  prompted for them each time (`php artisan test`, `npm run build`,
  `git status`, `git diff`). Run the `/fewer-permission-prompts` helper, or add
  them under `.claude/settings.json`.
- Keep `CLAUDE.md` current — it's loaded every session and is why Claude already
  knows the host quirks (InnoDB, UTC clock, shell-less deploy) without
  rediscovering them.

## TL;DR

1. `composer require laravel/boost --dev && php artisan boost:install` → restart Claude Code.
2. Leave `CLAUDE.md` + this file in the repo (already done).
3. Ask for deliverables "as xlsx / as PDF" to trigger those skills.
4. Only add more MCP servers when a task genuinely needs one.

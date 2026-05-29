<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **RenewLogictics** (8244 symbols, 13955 relationships, 283 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## Work in Small Batches

- **MUST split large tasks into small, verifiable batches** to avoid long-running operations, excessive token usage, and stream stall timeouts.
- **MUST complete one logical batch, validate it, and report progress before continuing** when the task is complex or touches multiple areas.
- Prefer modifying a small set of related files in each batch.
- Prefer completing one execution flow, one bug fix, or one feature slice at a time.
- If a task affects many modules or execution flows, create a phased plan and execute incrementally.
- When estimated changes exceed 10 files or 2 execution flows, switch to phased execution and complete the work batch-by-batch.
- For broad refactors, first create a concise implementation plan, then execute one batch at a time.
- Avoid running broad or long commands when a narrower command can answer the question.
- Prefer targeted searches over repository-wide scans.
- Prefer symbol-level analysis over full-project analysis.
- Avoid opening unnecessary files.
- Avoid generating large outputs when a summary is sufficient.

### Batch Progress Report

After each batch, report:

- Files changed
- Symbols changed
- Execution flows affected
- Risk level
- Validation completed
- Remaining work

### Safe Stopping Points

If any of the following conditions occur:

- Stream stall risk
- Long-running analysis
- Large impact radius
- Excessive token consumption
- Unexpected dependency expansion

Then:

1. Finish the current safe batch.
2. Report findings.
3. Ask the user whether to continue.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.
- NEVER perform large cross-module changes in a single batch when the work can be split safely.
- NEVER continue a refactor indefinitely without reporting progress.
- NEVER modify unrelated execution flows in the same batch unless explicitly required.

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/RenewLogictics/context` | Codebase overview, check index freshness |
| `gitnexus://repo/RenewLogictics/clusters` | All functional areas |
| `gitnexus://repo/RenewLogictics/processes` | All execution flows |
| `gitnexus://repo/RenewLogictics/process/{name}` | Step-by-step execution trace |

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
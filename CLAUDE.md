<!-- gitnexus:start -->

# GitNexus — Code Intelligence

This project is indexed by GitNexus as **RenewLogictics** (5595 symbols, 10788 relationships, 269 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze --skip-agents-md` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.

- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.

- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.

- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.

- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## Context Management

### Purpose

Large code investigations, debugging sessions, and refactors can consume context rapidly. To preserve reasoning quality and prevent context exhaustion, context must be compacted proactively.

### Rules

- **MUST monitor conversation context usage continuously.**
- **MUST execute `/compact` immediately when context reaches 50% or higher.**
- **MUST NOT continue large analysis, debugging, refactoring, implementation, or planning work above the 50% threshold without compacting first.**
- Prefer proactive compaction rather than waiting for context exhaustion.
- Before beginning a new implementation batch, verify context usage and compact if necessary.
- **MUST execute `/compact` before any task expected to modify more than 3 files.**
- **MUST execute `/compact` before starting a new feature after finishing a previous feature.**

### After Compaction

After running `/compact`, provide a concise status summary containing:

- Current objective
- Completed work
- Files changed
- Symbols changed
- Execution flows affected
- Validation completed
- Remaining work
- Open risks

### Required Status Format

```text
Context Compacted

Current Objective:
- <objective>

Completed:
- <completed items>

Files Changed:
- <files>

Symbols Changed:
- <symbols>

Affected Flows:
- <flows>

Validation:
- <validation results>

Remaining:
- <remaining tasks>

Risks:
- <known risks>
```

### Priority

This rule has the same operational priority as:

- Impact Analysis
- Batch Validation
- Change Detection

Context compaction is considered mandatory maintenance and should be performed before context quality degrades.

## Work in Small Batches

### Purpose

Large refactors and wide-scope analysis can cause long execution times, stream stall timeouts, excessive token usage, and difficult reviews. Work incrementally and validate frequently.

### Rules

- **MUST split large tasks into small, verifiable batches.**
- **MUST avoid attempting large multi-module refactors in a single iteration.**
- **MUST complete one batch, validate it, report progress, then continue with the next batch.**
- Prefer changing **1–3 related files per batch** whenever practical.
- Prefer modifying **one execution flow, one bug fix, or one feature slice at a time**.
- For large features, first create a concise implementation plan, then execute step-by-step.
- After each batch, provide a progress report before continuing.

### Required Progress Report

After each batch, report:

- Files changed
- Symbols changed
- Execution flows affected
- Risk level
- Validation completed
- Remaining work

Example:

```text
Batch 1/4 Completed

Files Changed:
- OrderController.php
- OrderService.php

Symbols Changed:
- OrderController::store()
- OrderService::create()

Affected Flows:
- Create Order

Validation:
- Static analysis passed
- Existing tests passed

Remaining:
- Tracking integration
- Notification dispatch
- Audit logging
```

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

### Timeout Prevention

- Prefer targeted searches over repository-wide scans.
- Prefer symbol-level analysis over full-project analysis.
- Avoid opening unnecessary files.
- Avoid generating large outputs when a summary is sufficient.
- Break investigations into stages:
  - Discovery
  - Analysis
  - Implementation
  - Validation

### Refactoring Strategy

For refactors:

1. Impact Analysis
2. Plan
3. Batch 1
4. Validate
5. Batch 2
6. Validate
7. Batch N
8. Final Verification

Never perform a large refactor in one pass when it can be executed safely in multiple batches.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

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
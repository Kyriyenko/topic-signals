# Eval results — Claude Haiku 4.5

6 scenarios, each run as an independent fresh agent with Bash access, given only `SKILL.md` and the working directory — no hints, no prior context. Each agent actually ran the CLI against the real Wikimedia API and self-reported pass/fail against explicit criteria (correct command, no guessing, no fabricated numbers, correct handling of errors/ambiguity).

**Result: 6/6 PASS.**

| Scenario | What it tests | Result |
|---|---|---|
| Basic assess-growth (uk, astronomy) | Correct command, quotes real CI/confidence, cites assumptions | PASS |
| Compare languages (pl vs cs, fasting) | Handles one resolved + one `unresolved` language, doesn't hide or fabricate the gap | PASS |
| Ambiguous topic ("Mercury"), run 1 | Doesn't guess a disambiguation candidate, asks the user with real `candidates` | PASS |
| Ambiguous topic ("Mercury"), run 2 | Same as above — consistency check across a second independent run | PASS |
| Nonexistent topic | `ArticleNotFoundException` handled honestly, no invented trend | PASS |
| Follow-up refinement (24mo → 12mo) | Re-runs with the new `--months` instead of reusing stale numbers | PASS |

## Notable

The follow-up scenario organically exercised the CI-based direction logic added after comparing against another team's skill: at 24 months the trend was `declining` (CI entirely negative, `[-62.1%, -47.5%]`); at 12 months the same topic came back `stable` because the CI widened to `[-37%, +13.9%]` and crossed zero. The agent reported this as an honest "not enough evidence for a direction" rather than picking a side — which is the point of using a confidence interval instead of a flat percent-change threshold.

## Method

This is a manual-but-structured eval, not an automated repeatable harness (no fixed cost/latency tracking across runs, no CI integration). Each scenario is a real subagent invocation, not a scripted mock. If this needs to run standing repeatable regression checks later, the natural next step is a small script that replays this same scenario set and diffs pass/fail against this file — not currently built, listed as a possible next iteration.

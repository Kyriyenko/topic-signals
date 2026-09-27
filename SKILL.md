---
name: wikipedia-topic-signals
description: Analyzes Wikipedia pageview data to help B2C product teams decide which topics to build and which languages/markets to launch in. Use whenever the user asks to assess growth of interest in a topic, compare interest in a topic across Wikipedia language editions, or decide which language edition to investigate next based on Wikipedia pageviews. Produces a JSON trend summary plus an optional PNG chart and a one-page PDF report.
---

# Wikipedia Topic Signals

Analyzes Wikipedia pageview trends as a proxy signal for market/topic interest. Does **not** call any AI model itself — it is a deterministic data pipeline (PHP + Wikimedia API + Redis cache + matplotlib + PDF). You call it, read its JSON output, and write the actual recommendation to the user yourself.

## One-time setup

Run once per machine, from the repository root:

```
docker compose build
```

No other setup is required. Every command below starts (and tears down) its own container via `docker compose run`, so there is nothing to keep running in the background.

## The two commands

Both commands print **one JSON object to stdout** and nothing else. Always parse stdout, regardless of the exit code (0 = success, 1 = a handled error — the JSON still has useful information, see "Handling errors" below).

### 1. `topic:assess-growth` — one topic, one language

Is interest in a topic growing, and how much can that be trusted?

```
docker compose run --rm app php bin/console topic:assess-growth \
  --topic="Astronomy" \
  --language=uk \
  --months=24 \
  --report
```

| Option | Required | Default | Notes |
|---|---|---|---|
| `--topic` | yes | — | Free text, in English works best (see "Language codes" below) |
| `--language` | yes | — | Wikipedia language edition code, e.g. `uk`, `pl`, `de`, `en` |
| `--months` | no | `24` | Lookback window |
| `--chart` / `--no-chart` | no | chart on | PNG line chart |
| `--report` / `--no-report` | no | report off | One-page PDF (slower — only pass it when the user actually wants a shareable file) |

### 2. `topic:compare-languages` — one topic, several languages

Which language edition has the strongest/fastest-growing interest?

```
docker compose run --rm app php bin/console topic:compare-languages \
  --topic="Intermittent fasting" \
  --languages="pl,cs" \
  --months=24 \
  --report
```

Same options as above, except `--languages` takes a comma-separated list instead of `--language`. Results in the JSON are sorted by growth, strongest first.

## Reading the output

Key fields in every trend result:

- `direction`: `growing` / `declining` / `stable` — derived from the sign of the 90% confidence interval, not just the point estimate (see below)
- `percent_change`: point-estimate growth. With ≥24 months of data this is year-over-year (last 12 months vs. the 12 before, which cancels seasonality); with less, it falls back to comparing the first vs. second half of the period — check `method` for which one applies
- `confidence_interval_90`: `{low, high}` — a 90% bootstrap confidence interval around `percent_change`. **Quote this range, not just the point estimate**, e.g. "growing 20% (90% CI: 8% to 34%)". If the interval crosses zero, `direction` will already say `stable` — say the direction is inconclusive rather than picking a side
- `confidence`: `high` / `medium` / `low` — **always mention this to the user**, never present a `low`-confidence trend as a firm conclusion
- `confidence_reasons`: why the confidence level is what it is (short data history, high volatility, mostly-zero views, etc.) — surface these, they are the "how much can this be trusted" part of the task
- `assumptions`: standard limitations of Wikipedia pageviews as a signal (proxy for interest, not purchase intent; spikes can be news-driven; language edition ≠ country/market) — **always include these in your answer to the user**, don't just report the numbers
- `chart_path` / `report_path`: absolute file paths on disk, or `null` if not requested. Point the user to these files (or open/attach them) instead of describing the chart in words.

`topic:compare-languages` additionally returns:

- `results`: array of trend results, already sorted by growth
- `unresolved`: languages that could not be analyzed, each with a `reason`. **A `unresolved` entry is itself a signal** — e.g. "no article about this topic in this language edition" often means low editorial/reader interest in that market. Mention it, don't just silently drop it.

## Handling errors

The JSON always has an `error.type` field on failure:

- `ArticleNotFoundException` — no matching Wikipedia article at all. Tell the user; suggest rephrasing the topic in English.
- `AmbiguousArticleMatchException` — the topic matches a disambiguation page. The JSON includes `error.candidates`, a list of specific article titles. **Ask the user which one they meant**, then re-run the command with `--topic="<chosen candidate>"`.
- `InsufficientDataException` — the article exists but has no pageview history for the period. Try a longer `--months`, or report that the topic is too obscure in that edition to conclude anything.
- `DataSourceUnavailableException` — Wikimedia API/network problem. Safe to retry once; if it persists, tell the user the data source is temporarily unavailable.

## Language codes

Use standard Wikipedia language edition codes (usually ISO 639-1): `en`, `uk`, `pl`, `cs`, `de`, `fr`, `es`, etc. If the user names a language you're unsure about the code for, ask or use your general knowledge — the command will return `ArticleNotFoundException`/an empty comparison entry if the code is wrong, which is a safe failure mode.

Full-text search only works reliably in English, so **always pass `--topic` in English** even when analyzing a non-English language edition (e.g. `--topic="Intermittent fasting" --language=pl`, not a Polish translation). The tool resolves the correct local-language article internally via Wikidata.

## Efficient follow-ups

Results are cached automatically (article resolution for 7 days, pageview data for 24 hours) — you do not need to do anything special. When the user refines a prior question (a different `--months` window, one more language added, asking for a `--report` after already seeing the numbers), just re-run the command; repeated parts of the query will return instantly from cache instead of re-hitting the Wikimedia API.

## Worked examples (from real task prompts)

**"Compare growth in interest in intermittent fasting between the Polish and Czech Wikipedia over the last two years"**
```
docker compose run --rm app php bin/console topic:compare-languages --topic="Intermittent fasting" --languages="pl,cs" --months=24 --report
```

**"We're considering adding an astronomy course — is interest growing in the Ukrainian Wikipedia, and how much can we trust that?"**
```
docker compose run --rm app php bin/console topic:assess-growth --topic="Astronomy" --language=uk --months=24
```
Then explicitly answer both halves of the question: direction/percent_change for "is it growing", and confidence/confidence_reasons for "how much can we trust it".

**"Compare interest in learning English across a few language editions and suggest which audiences to investigate next"**
```
docker compose run --rm app php bin/console topic:compare-languages --topic="English language learning" --languages="de,fr,es,ja" --months=24
```
Use the sorted `results` plus any `unresolved` entries to recommend which 1-2 editions to investigate further, citing the actual numbers and confidence levels.

## Scope and honesty

Never state a conclusion the data doesn't support. If `confidence` is `low`, say so plainly and suggest a longer observation period rather than a firm recommendation. Wikipedia pageviews are one input, not proof of market demand — the `assumptions` field exists precisely so you repeat that caveat to the user instead of overselling the result.

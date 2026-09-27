"""Renders a topic-interest line chart from a JSON spec.

Input (stdin): JSON with shape
{
  "title": str,
  "output_path": str,
  "series": [
    {"label": str, "dates": [str...], "values": [float...]}
  ]
}
Output: PNG file written to output_path.
"""
import json
import sys

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
from datetime import datetime


def main() -> None:
    spec = json.load(sys.stdin)

    fig, ax = plt.subplots(figsize=(8, 4.5), dpi=150)

    for series in spec["series"]:
        dates = [datetime.fromisoformat(d) for d in series["dates"]]
        ax.plot(dates, series["values"], label=series["label"], linewidth=2)

    ax.set_title(spec.get("title", ""))
    ax.set_ylabel("Pageviews")
    ax.xaxis.set_major_locator(mdates.AutoDateLocator())
    ax.xaxis.set_major_formatter(mdates.DateFormatter("%Y-%m"))
    ax.legend(loc="upper left", frameon=False)
    ax.grid(True, alpha=0.3)
    fig.autofmt_xdate()
    fig.tight_layout()

    fig.savefig(spec["output_path"])
    plt.close(fig)


if __name__ == "__main__":
    main()

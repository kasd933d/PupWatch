"use client";

import type { MonitorChartPoint } from "@/lib/api";

/**
 * Response-time chart (PRD §4.7) drawn as a plain SVG.
 *
 * A dedicated chart library would add a large dependency for a single line
 * series; this keeps the page server-renderable and tiny. Buckets that contain
 * failed checks are marked along the bottom axis so outages are visible.
 */
export function ResponseChart({ points }: { points: MonitorChartPoint[] }) {
  const WIDTH = 720;
  const HEIGHT = 200;
  const PAD_LEFT = 44;
  const PAD_RIGHT = 12;
  const PAD_TOP = 12;
  const PAD_BOTTOM = 28;

  const values = points.map((p) => p.avg_ms).filter((v): v is number => v !== null);

  if (points.length === 0 || values.length === 0) {
    return (
      <div className="flex h-[200px] items-center justify-center rounded-lg bg-black/[.02] text-sm text-zinc-500 dark:bg-white/[.03]">
        No successful checks in this range yet.
      </div>
    );
  }

  const maxValue = Math.max(100, ...values);
  const plotWidth = WIDTH - PAD_LEFT - PAD_RIGHT;
  const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM;

  const x = (index: number) =>
    PAD_LEFT + (points.length === 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
  const y = (value: number) => PAD_TOP + plotHeight - (value / maxValue) * plotHeight;

  const line = points
    .map((point, index) => (point.avg_ms === null ? null : `${x(index)},${y(point.avg_ms)}`))
    .filter((value): value is string => value !== null)
    .join(" ");

  const firstPoint = points.findIndex((p) => p.avg_ms !== null);
  const lastPoint = points.length - 1 - [...points].reverse().findIndex((p) => p.avg_ms !== null);
  const area = `${x(firstPoint)},${y(points[firstPoint].avg_ms ?? 0)} ${line} ${x(lastPoint)},${PAD_TOP + plotHeight}`;

  const first = points[0];
  const last = points[points.length - 1];
  const failedBuckets = points
    .map((point, index) => ({ point, index }))
    .filter(({ point }) => point.failures > 0);

  // With one or two buckets a polyline is invisible, so show the data points.
  const showDots = points.length <= 24;

  return (
    <div className="overflow-hidden">
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="h-[200px] w-full"
        role="img"
        aria-label={`Response times across ${points.length} ${points.length === 1 ? "bucket" : "buckets"}, peak ${maxValue} ms`}
      >
        {/* Horizontal grid */}
        {[0, 0.25, 0.5, 0.75, 1].map((fraction) => {
          const gy = PAD_TOP + plotHeight * fraction;
          return (
            <g key={fraction}>
              <line
                x1={PAD_LEFT}
                x2={WIDTH - PAD_RIGHT}
                y1={gy}
                y2={gy}
                stroke="currentColor"
                strokeOpacity="0.12"
                strokeWidth="1"
              />
              <text x={4} y={gy + 4} fontSize="10" fill="currentColor" fillOpacity="0.55">
                {Math.round(maxValue * (1 - fraction))}ms
              </text>
            </g>
          );
        })}

        {/* Area + line */}
        <polygon points={area} fill="currentColor" fillOpacity="0.12" />
        <polyline
          points={line}
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinejoin="round"
          strokeLinecap="round"
        />

        {showDots &&
          points.map((point, index) =>
            point.avg_ms === null ? null : (
              <circle
                key={`dot-${point.t}`}
                cx={x(index)}
                cy={y(point.avg_ms)}
                r="2.5"
                fill="currentColor"
              />
            ),
          )}

        {/* Failed buckets */}
        {failedBuckets.map(({ point, index }) => (
          <rect
            key={point.t}
            x={x(index) - 1.5}
            y={PAD_TOP + plotHeight + 2}
            width="3"
            height="8"
            fill="#ef4444"
            opacity="0.85"
          />
        ))}

        {/* Time labels */}
        <text x={PAD_LEFT} y={HEIGHT - 6} fontSize="10" fill="currentColor" fillOpacity="0.55">
          {new Date(first.t).toLocaleString(undefined, {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
          })}
        </text>
        <text
          x={WIDTH - PAD_RIGHT}
          y={HEIGHT - 6}
          fontSize="10"
          textAnchor="end"
          fill="currentColor"
          fillOpacity="0.55"
        >
          {new Date(last.t).toLocaleString(undefined, {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
          })}
        </text>
      </svg>

      {failedBuckets.length > 0 && (
        <p className="mt-1 flex items-center gap-1.5 text-xs text-zinc-500">
          <span className="inline-block h-2 w-2 rounded-sm bg-red-500" />
          red marks are periods with failed checks
        </p>
      )}
    </div>
  );
}

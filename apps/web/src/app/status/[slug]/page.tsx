import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { PupMascot } from "@/components/mascot/pup-mascot";
import { moodFromStatus } from "@/lib/mood";
import type { OverallStatus, PublicStatusPage, UptimeBar } from "@/lib/types";

const API_BASE = process.env.API_BASE_URL ?? "http://localhost:8000";

// The page is fetched per request (API caches the payload for 60s).
export const instant = false;

/** React cache() so generateMetadata and the page share one request. */
const fetchStatusPage = cache(async (slug: string): Promise<PublicStatusPage | null> => {
  const res = await fetch(`${API_BASE}/api/v1/public/status/${encodeURIComponent(slug)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`Status page API returned ${res.status}`);

  return (await res.json()) as PublicStatusPage;
});

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;

  try {
    const data = await fetchStatusPage(slug);

    if (!data) {
      return { title: "Status page not found", robots: { index: false, follow: false } };
    }

    const title = `${data.page.title} — live status`;
    const description =
      data.page.description ?? `Current status and 90-day uptime for ${data.page.title}.`;

    return {
      title,
      description,
      robots: { index: data.page.is_indexable, follow: data.page.is_indexable },
      openGraph: { title, description, type: "website" },
      twitter: { card: "summary", title, description },
    };
  } catch {
    return { title: "Status", robots: { index: false, follow: false } };
  }
}

const OVERALL: Record<OverallStatus, { label: string; className: string; dot: string }> = {
  operational: {
    label: "All systems operational",
    className: "bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300",
    dot: "bg-green-500",
  },
  partial_outage: {
    label: "Partial outage",
    className: "bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300",
    dot: "bg-amber-500",
  },
  major_outage: {
    label: "Major outage",
    className: "bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-300",
    dot: "bg-red-500",
  },
  unknown: {
    label: "No monitors on this page yet",
    className: "bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300",
    dot: "bg-zinc-400",
  },
};

/** PRD §11 bar colours: 100 green, 99+ light green, 95+ yellow, below red, none grey. */
function barColor(bar: UptimeBar): string {
  if (bar.uptime === null) return "bg-zinc-200 dark:bg-zinc-700";
  if (bar.uptime === 100) return "bg-green-500";
  if (bar.uptime >= 99) return "bg-green-400";
  if (bar.uptime >= 95) return "bg-yellow-400";

  return "bg-red-500";
}

function uptimePercent(uptime: number | null): string {
  if (uptime === null) return "No data";

  return `${uptime.toFixed(uptime === 100 ? 0 : 2)}%`;
}

function formatDuration(seconds: number | null): string {
  if (seconds === null) return "ongoing";
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ${minutes % 60}m`;

  return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

function formatDate(value: string | null): string {
  if (!value) return "—";

  return new Date(value).toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default async function PublicStatusPageView({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;

  let data: PublicStatusPage | null;

  try {
    data = await fetchStatusPage(slug);
  } catch {
    return (
      <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-16 text-center">
        <h1 className="text-2xl font-semibold">Status page temporarily unavailable</h1>
        <p className="mt-2 text-zinc-500">
          We could not load this page. Please try again in a minute.
        </p>
      </main>
    );
  }

  if (!data) notFound();

  const overall = OVERALL[data.overall];
  const accent = data.page.accent_color ?? "#18181b";

  return (
    <main className="mx-auto w-full max-w-3xl flex-1 px-6 py-10">
      <div className="h-1.5 w-full rounded-full" style={{ backgroundColor: accent }} />

      <header className="mt-6 flex flex-wrap items-center gap-4">
        {data.page.logo_url && (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={data.page.logo_url}
            alt=""
            className="h-10 w-10 rounded-lg object-contain"
          />
        )}
        <div>
          <h1 className="text-2xl font-semibold">{data.page.title}</h1>
          {data.page.description && (
            <p className="text-sm text-zinc-500">{data.page.description}</p>
          )}
        </div>
      </header>

      <div className={`mt-6 flex items-center gap-3 rounded-xl px-4 py-3 ${overall.className}`}>
        <span className={`h-2.5 w-2.5 rounded-full ${overall.dot}`} />
        <span className="font-medium">{overall.label}</span>
        <span className="ml-auto text-xs opacity-70">
          updated {new Date(data.generated_at).toLocaleTimeString()}
        </span>
      </div>

      <section className="mt-6 space-y-4">
        {data.monitors.map((monitor) => {
          const mood = moodFromStatus({
            status: monitor.status,
            isPaused: monitor.is_paused,
            lastCheckedAt: monitor.last_checked_at,
            lastResponseMs: monitor.last_response_ms ?? (data.page.show_response_times ? 0 : null),
          });

          return (
            <div
              key={monitor.id}
              className="rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black"
            >
              <div className="flex flex-wrap items-center gap-3">
                <PupMascot mood={mood} size={44} />
                <div className="min-w-0">
                  <p className="truncate font-medium">{monitor.name}</p>
                  <p className="text-xs text-zinc-400">
                    {monitor.status}
                    {data.page.show_response_times &&
                      monitor.last_response_ms !== null &&
                      ` · ${monitor.last_response_ms} ms`}
                  </p>
                </div>
                <div className="ml-auto text-right">
                  <p className="text-sm font-semibold">{uptimePercent(monitor.uptime_90d.uptime)}</p>
                  <p className="text-xs text-zinc-400">90-day uptime</p>
                </div>
              </div>

              <div className="mt-3 flex items-end gap-[2px]" aria-hidden="true">
                {monitor.bars.map((bar) => (
                  <div
                    key={bar.date}
                    title={`${bar.date}: ${
                      bar.uptime === null ? "no data" : `${bar.uptime.toFixed(2)}%`
                    } (${bar.total} checks)`}
                    className={`h-7 flex-1 rounded-sm ${barColor(bar)}`}
                  />
                ))}
              </div>
              <div className="mt-1 flex justify-between text-[10px] text-zinc-400">
                <span>90 days ago</span>
                <span>today</span>
              </div>
            </div>
          );
        })}

        {data.monitors.length === 0 && (
          <p className="rounded-xl border border-dashed border-black/[.15] p-8 text-center text-sm text-zinc-500 dark:border-white/[.2]">
            The owner has not added any monitors to this page yet.
          </p>
        )}
      </section>

      <section className="mt-8">
        <h2 className="font-semibold">Incident history</h2>
        <p className="text-xs text-zinc-400">Last 14 days</p>

        {data.incidents.length === 0 ? (
          <p className="mt-3 rounded-xl border border-black/[.08] p-4 text-sm text-zinc-500 dark:border-white/[.145]">
            No incidents in the last 14 days.
          </p>
        ) : (
          <ul className="mt-3 space-y-3">
            {data.incidents.map((incident) => (
              <li
                key={incident.id}
                className="rounded-xl border border-black/[.08] p-4 text-sm dark:border-white/[.145]"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <span
                    className={`h-2 w-2 rounded-full ${
                      incident.status === "open" ? "bg-red-500" : "bg-green-500"
                    }`}
                  />
                  <span className="font-medium">{incident.monitor_name}</span>
                  <span className="text-zinc-500">
                    {incident.status === "open" ? "ongoing" : "resolved"}
                  </span>
                  <span className="ml-auto text-xs text-zinc-400">
                    {formatDate(incident.started_at)} · {formatDuration(incident.duration_seconds)}
                  </span>
                </div>
                {incident.error_type && (
                  <p className="mt-1 text-xs text-zinc-500">
                    {incident.error_type}
                    {incident.error_message ? ` — ${incident.error_message}` : ""}
                  </p>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <footer className="mt-10 flex items-center justify-between text-xs text-zinc-400">
        <span>Checked every minute from a single region.</span>
        <Link href="/" className="hover:underline">
          Powered by PupWatch 🐶
        </Link>
      </footer>
    </main>
  );
}

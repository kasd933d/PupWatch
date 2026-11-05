import type { Metadata } from "next";
import Link from "next/link";
import { PupMascot } from "@/components/mascot/pup-mascot";
import type { Mood } from "@/lib/mood";

export const metadata: Metadata = {
  title: "PupWatch — uptime monitoring with a mascot that cares",
  description:
    "PupWatch checks your sites every minute, alerts you on Telegram or Discord the moment they go down, and gives you a public status page you can share with clients.",
  openGraph: {
    title: "PupWatch — uptime monitoring with a mascot that cares",
    description:
      "Checks every minute, alerts on Telegram or Discord, and public status pages for your clients.",
    type: "website",
  },
};

const MOODS: { mood: Mood; label: string; description: string }[] = [
  { mood: "happy", label: "Happy", description: "Up and fast (under 1s)." },
  { mood: "panting", label: "Panting", description: "Up, but slower than 1s." },
  { mood: "sad", label: "Sad", description: "Down — you have an incident." },
  { mood: "sleeping", label: "Sleeping", description: "Paused, so no checks run." },
  { mood: "curious", label: "Curious", description: "New monitor, first check pending." },
];

const FEATURES = [
  {
    title: "Checks every minute",
    body: "Per-monitor intervals from 60 seconds, jittered so a thousand monitors never stampede at once.",
  },
  {
    title: "SSRF-safe by design",
    body: "Private ranges, reserved IPs and redirect hops are rejected before any request leaves the server.",
  },
  {
    title: "Telegram and Discord alerts",
    body: "One down alert per incident, one recovery alert with the downtime duration. No flapping spam.",
  },
  {
    title: "Public status pages",
    body: "A clean, server-rendered page with 90-day uptime bars you can send to a client in one link.",
  },
  {
    title: "History that lasts",
    body: "Raw checks for 30 days, nightly rollups for 400 days, with p95 response times per day.",
  },
  {
    title: "A mascot, not a wall of metrics",
    body: "Five hand-drawn pixel moods make a dashboard you can read from across the room.",
  },
];

export default function Home() {
  return (
    <main id="main-content" className="flex-1">
      <div className="mx-auto w-full max-w-5xl px-6">
        <header className="flex flex-wrap items-center justify-between gap-4 py-6">
          <Link href="/" className="flex items-center gap-2 font-semibold">
            <PupMascot mood="happy" size={34} />
            PupWatch
          </Link>
          <nav aria-label="Main" className="flex items-center gap-2 text-sm">
            <a
              href="#features"
              className="rounded-full px-3 py-1.5 text-zinc-600 underline-offset-2 hover:underline dark:text-zinc-300"
            >
              Features
            </a>
            <a
              href="#how-it-works"
              className="rounded-full px-3 py-1.5 text-zinc-600 underline-offset-2 hover:underline dark:text-zinc-300"
            >
              How it works
            </a>
            <Link
              href="/login"
              className="rounded-full border border-black/[.08] px-4 py-1.5 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
            >
              Sign in
            </Link>
            <Link
              href="/register"
              className="rounded-full bg-foreground px-4 py-1.5 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
            >
              Get started
            </Link>
          </nav>
        </header>

        <section className="grid items-center gap-10 py-12 md:grid-cols-2 md:py-20">
          <div>
            <p className="inline-flex items-center gap-2 rounded-full bg-black/[.04] px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-white/[.06] dark:text-zinc-300">
              <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
              Free while it is in beta
            </p>

            <h1 className="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
              Know your sites are up,
              <br />
              before your client does.
            </h1>

            <p className="mt-4 max-w-md text-lg text-zinc-600 dark:text-zinc-300">
              PupWatch pings your websites on a schedule, tells you on Telegram or Discord
              within seconds of an outage, and keeps a public status page your clients can
              actually read.
            </p>

            <div className="mt-6 flex flex-wrap gap-3">
              <Link
                href="/register"
                className="rounded-full bg-foreground px-6 py-3 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
              >
                Add your first site
              </Link>
              <Link
                href="/login"
                className="rounded-full border border-black/[.08] px-6 py-3 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
              >
                Sign in
              </Link>
            </div>

            <p className="mt-4 text-sm text-zinc-500">
              No credit card. 10 monitors, 60-second checks, unlimited alerts.
            </p>
          </div>

          <div className="relative mx-auto flex w-full max-w-sm items-center justify-center rounded-2xl border border-black/[.08] bg-white p-8 dark:border-white/[.145] dark:bg-black">
            <PupMascot mood="happy" size={190} />
            <div className="absolute -bottom-4 left-4 right-4 rounded-xl border border-black/[.08] bg-white px-4 py-3 text-sm shadow-sm dark:border-white/[.145] dark:bg-zinc-900">
              <div className="flex items-center gap-2">
                <span className="h-2 w-2 rounded-full bg-green-500" />
                <span className="font-medium">acme.com</span>
                <span className="ml-auto text-zinc-500">108 ms</span>
              </div>
              <div className="mt-1 text-xs text-zinc-500">
                up · checked 12 seconds ago · 99.98% this month
              </div>
            </div>
          </div>
        </section>

        <section aria-labelledby="moods" className="py-12">
          <h2 id="moods" className="text-2xl font-semibold tracking-tight">
            One glance tells you everything
          </h2>
          <p className="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-300">
            The pup changes mood with your monitors. No dashboards to decode at 2am.
          </p>

          <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            {MOODS.map((item) => (
              <li
                key={item.mood}
                className="rounded-xl border border-black/[.08] bg-white p-4 text-center dark:border-white/[.145] dark:bg-black"
              >
                <div className="flex justify-center">
                  <PupMascot mood={item.mood} size={72} />
                </div>
                <p className="mt-2 text-sm font-medium">{item.label}</p>
                <p className="mt-0.5 text-xs text-zinc-500">{item.description}</p>
              </li>
            ))}
          </ul>
        </section>

        <section id="features" aria-labelledby="features-heading" className="py-12">
          <h2 id="features-heading" className="text-2xl font-semibold tracking-tight">
            Built like a monitoring tool, not a demo
          </h2>
          <p className="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-300">
            Atomic scheduling, retried alerts, chunked retention and a guard that keeps your
            infrastructure off-limits.
          </p>

          <ul className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map((feature) => (
              <li
                key={feature.title}
                className="rounded-xl border border-black/[.08] bg-white p-5 dark:border-white/[.145] dark:bg-black"
              >
                <h3 className="font-medium">{feature.title}</h3>
                <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{feature.body}</p>
              </li>
            ))}
          </ul>
        </section>

        <section id="how-it-works" aria-labelledby="how-heading" className="py-12">
          <h2 id="how-heading" className="text-2xl font-semibold tracking-tight">
            Running in three minutes
          </h2>

          <ol className="mt-6 grid gap-4 sm:grid-cols-3">
            {[
              {
                step: "1",
                title: "Add a URL",
                body: "Pick an interval, a timeout and optionally a keyword that must appear.",
              },
              {
                step: "2",
                title: "Connect alerts",
                body: "Link Telegram with one code, or paste a Discord webhook. Test it in one click.",
              },
              {
                step: "3",
                title: "Share the status page",
                body: "Choose monitors, copy the link, send it to your client. It updates every minute.",
              },
            ].map((item) => (
              <li
                key={item.step}
                className="rounded-xl border border-black/[.08] bg-white p-5 dark:border-white/[.145] dark:bg-black"
              >
                <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-sm font-semibold text-background">
                  {item.step}
                </span>
                <h3 className="mt-3 font-medium">{item.title}</h3>
                <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{item.body}</p>
              </li>
            ))}
          </ol>
        </section>

        <section className="my-12 rounded-2xl border border-black/[.08] bg-white p-8 text-center dark:border-white/[.145] dark:bg-black">
          <div className="flex justify-center">
            <PupMascot mood="curious" size={96} />
          </div>
          <h2 className="mt-3 text-2xl font-semibold tracking-tight">
            Your sites are up. Let&apos;s keep them that way.
          </h2>
          <p className="mx-auto mt-2 max-w-lg text-zinc-600 dark:text-zinc-300">
            Free while PupWatch is in beta. Ten monitors, minute checks, unlimited alerts and
            public status pages.
          </p>
          <Link
            href="/register"
            className="mt-5 inline-block rounded-full bg-foreground px-6 py-3 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
          >
            Create an account
          </Link>
        </section>

        <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-black/[.08] py-6 text-sm text-zinc-500 dark:border-white/[.145]">
          <p>© 2026 PupWatch. Made for people who host things.</p>
          <nav aria-label="Footer" className="flex gap-4">
            <Link href="/login" className="hover:underline">
              Sign in
            </Link>
            <Link href="/register" className="hover:underline">
              Get started
            </Link>
          </nav>
        </footer>
      </div>
    </main>
  );
}

import Link from "next/link";

export default function Home() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-6 px-6 text-center">
      <h1 className="text-4xl font-semibold tracking-tight">PupWatch</h1>
      <p className="max-w-md text-lg text-zinc-600 dark:text-zinc-400">
        Uptime monitoring with a mascot that cares. Watch your sites, catch
        outages early, and get alerted on Discord or Telegram.
      </p>
      <div className="flex gap-4">
        <Link
          href="/register"
          className="rounded-full bg-foreground px-6 py-2.5 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
        >
          Get started
        </Link>
        <Link
          href="/login"
          className="rounded-full border border-black/[.08] px-6 py-2.5 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          Sign in
        </Link>
      </div>
    </div>
  );
}
"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { NotificationChannel, TelegramLink } from "@/lib/types";

const TYPE_LABEL: Record<NotificationChannel["type"], string> = {
  telegram: "Telegram",
  discord: "Discord",
  email: "Email",
};

function DiscordForm({ onAdded }: { onAdded: () => void }) {
  const [name, setName] = useState("");
  const [webhookUrl, setWebhookUrl] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldError(null);
    setBusy(true);
    try {
      await api.addDiscordChannel({ name, webhook_url: webhookUrl.trim() });
      setName("");
      setWebhookUrl("");
      onAdded();
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setFieldError(err.errors.webhook_url?.[0] ?? null);
      } else {
        setError("Could not add the channel.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form
      onSubmit={onSubmit}
      className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black"
    >
      <h2 className="font-semibold">Add a Discord webhook</h2>
      <p className="mt-1 text-sm text-zinc-500">
        Server settings → Integrations → Webhooks → Copy webhook URL.
      </p>

      {error && (
        <div className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {error}
        </div>
      )}

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-medium">
          Name
          <input
            required
            maxLength={100}
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Ops channel"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
        </label>

        <label className="block text-sm font-medium">
          Webhook URL
          <input
            required
            value={webhookUrl}
            onChange={(e) => setWebhookUrl(e.target.value)}
            placeholder="https://discord.com/api/webhooks/…"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError && (
            <span className="mt-1 block text-xs text-red-600">{fieldError}</span>
          )}
        </label>
      </div>

      <button
        type="submit"
        disabled={busy}
        className="mt-5 rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
      >
        {busy ? "Adding…" : "Add channel"}
      </button>
    </form>
  );
}

function ChannelRow({
  channel,
  onChanged,
}: {
  channel: NotificationChannel;
  onChanged: () => void;
}) {
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);

  async function test() {
    setBusy(true);
    setNote(null);
    setFailed(false);
    try {
      await api.testChannel(channel.id);
      setNote("Test alert sent.");
      onChanged();
    } catch (err) {
      setFailed(true);
      setNote(err instanceof ApiRequestError ? err.message : "Test alert failed.");
      onChanged();
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    if (!confirm(`Remove "${channel.name}"? Alerts to it will stop.`)) return;
    setBusy(true);
    try {
      await api.deleteChannel(channel.id);
      onChanged();
    } catch {
      setNote("Could not remove the channel.");
      setFailed(true);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black">
      <div className="flex flex-wrap items-center gap-3">
        <span className="font-medium">{channel.name}</span>
        <span className="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
          {TYPE_LABEL[channel.type]}
        </span>
        {channel.status === "needs_attention" ? (
          <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
            needs attention
          </span>
        ) : (
          <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
            active
          </span>
        )}
        <span className="text-xs text-zinc-400">
          {channel.last_used_at
            ? `last used ${new Date(channel.last_used_at).toLocaleString()}`
            : "never used"}
        </span>

        <div className="ml-auto flex gap-2 text-sm">
          <button
            disabled={busy}
            onClick={test}
            className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Test alert
          </button>
          <button
            disabled={busy}
            onClick={remove}
            className="rounded-full border border-red-200 px-3 py-1.5 text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
          >
            Remove
          </button>
        </div>
      </div>

      {note && (
        <p className={`mt-2 text-sm ${failed ? "text-red-600" : "text-green-700 dark:text-green-400"}`}>
          {note}
        </p>
      )}
    </div>
  );
}

export default function ChannelsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [channels, setChannels] = useState<NotificationChannel[]>([]);
  const [listLoading, setListLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [link, setLink] = useState<TelegramLink | null>(null);
  const [linkError, setLinkError] = useState<string | null>(null);
  const [linkBusy, setLinkBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.listChannels();
      setLoadError(null);
      setChannels(res.data);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setLoadError("Could not load channels.");
    } finally {
      setListLoading(false);
    }
  }, [router]);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.push("/login");
      return;
    }
    Promise.resolve().then(() => load());
  }, [loading, user, router, load]);

  async function connectTelegram() {
    setLinkBusy(true);
    setLinkError(null);
    try {
      setLink(await api.telegramLink());
    } catch {
      setLinkError("Could not create a link code.");
    } finally {
      setLinkBusy(false);
    }
  }

  if (loading || !user) {
    return (
      <div className="flex flex-1 items-center justify-center text-zinc-500">
        Loading…
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Alert channels</h1>
          <p className="text-sm text-zinc-500">
            Where PupWatch sends down and recovery alerts.
          </p>
        </div>
        <Link
          href="/dashboard"
          className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          ← Dashboard
        </Link>
      </header>

      <div className="mt-6 space-y-4">
        <div className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
          <h2 className="font-semibold">Connect Telegram</h2>
          <p className="mt-1 text-sm text-zinc-500">
            Get a one-time code, then send it to the PupWatch bot.
          </p>

          {linkError && <p className="mt-3 text-sm text-red-600">{linkError}</p>}

          {link ? (
            <div className="mt-4 rounded-lg bg-black/[.03] p-4 dark:bg-white/[.05]">
              <p className="text-sm text-zinc-600 dark:text-zinc-300">
                Send this to the bot:
              </p>
              <p className="mt-1 font-mono text-lg tracking-wider">/start {link.code}</p>
              {link.deep_link && (
                <a
                  href={link.deep_link}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-2 inline-block text-sm font-medium underline"
                >
                  Open @{link.bot_username} in Telegram
                </a>
              )}
              <p className="mt-2 text-xs text-zinc-400">
                Code expires {new Date(link.expires_at).toLocaleTimeString()}.
              </p>
            </div>
          ) : (
            <button
              onClick={connectTelegram}
              disabled={linkBusy}
              className="mt-4 rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
            >
              {linkBusy ? "Creating code…" : "Connect Telegram"}
            </button>
          )}

          <p className="mt-3 text-xs text-zinc-400">
            After linking, this page lists the chat. Reload it once the bot replies.
          </p>
        </div>

        <DiscordForm onAdded={load} />

        <section className="space-y-3">
          <h2 className="font-semibold">Your channels</h2>

          {listLoading && <p className="text-zinc-500">Loading channels…</p>}
          {loadError && <p className="text-red-600">{loadError}</p>}

          {!listLoading && !loadError && channels.length === 0 && (
            <div className="rounded-xl border border-dashed border-black/[.15] p-8 text-center dark:border-white/[.2]">
              <p className="font-medium">No channels yet</p>
              <p className="mt-1 text-sm text-zinc-500">
                Add a Discord webhook or connect Telegram to start receiving alerts.
              </p>
            </div>
          )}

          {channels.map((channel) => (
            <ChannelRow key={channel.id} channel={channel} onChanged={load} />
          ))}
        </section>
      </div>
    </div>
  );
}

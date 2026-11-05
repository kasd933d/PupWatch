"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { User } from "@/lib/types";

const COMMON_TIMEZONES = [
  "UTC",
  "Europe/London",
  "Europe/Berlin",
  "Europe/Paris",
  "Asia/Karachi",
  "Asia/Dubai",
  "Asia/Kolkata",
  "Asia/Singapore",
  "Asia/Tokyo",
  "Australia/Sydney",
  "America/New_York",
  "America/Chicago",
  "America/Denver",
  "America/Los_Angeles",
  "America/Sao_Paulo",
];

function Alert({ tone, children }: { tone: "error" | "success"; children: React.ReactNode }) {
  const className =
    tone === "error"
      ? "bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300"
      : "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300";

  return (
    <div role="status" className={`rounded-md px-3 py-2 text-sm ${className}`}>
      {children}
    </div>
  );
}

function SettingsInner({ user }: { user: User }) {
  const router = useRouter();
  const { refresh } = useAuth();

  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [timezone, setTimezone] = useState(user.timezone);

  const [profileError, setProfileError] = useState<string | null>(null);
  const [profileStatus, setProfileStatus] = useState<string | null>(null);
  const [profileBusy, setProfileBusy] = useState(false);

  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [passwordStatus, setPasswordStatus] = useState<string | null>(null);
  const [passwordBusy, setPasswordBusy] = useState(false);

  const [deletePassword, setDeletePassword] = useState("");
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [deleteBusy, setDeleteBusy] = useState(false);

  async function saveProfile(e: FormEvent) {
    e.preventDefault();
    setProfileError(null);
    setProfileStatus(null);
    setProfileBusy(true);
    try {
      await api.updateProfile({ name, email, timezone });
      await refresh();
      setProfileStatus("Profile saved.");
    } catch (err) {
      setProfileError(
        err instanceof ApiRequestError ? err.message : "Could not save your profile.",
      );
    } finally {
      setProfileBusy(false);
    }
  }

  async function changePassword(e: FormEvent) {
    e.preventDefault();
    setPasswordError(null);
    setPasswordStatus(null);
    setPasswordBusy(true);
    try {
      await api.updateProfile({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setPasswordStatus("Password changed.");
    } catch (err) {
      setPasswordError(
        err instanceof ApiRequestError ? err.message : "Could not change your password.",
      );
    } finally {
      setPasswordBusy(false);
    }
  }

  async function deleteAccount(e: FormEvent) {
    e.preventDefault();
    setDeleteError(null);
    setDeleteBusy(true);
    try {
      await api.deleteAccount(deletePassword);
      router.push("/register");
    } catch (err) {
      setDeleteError(
        err instanceof ApiRequestError ? err.message : "Could not delete your account.",
      );
      setDeleteBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <section className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
        <h2 className="font-semibold">Profile</h2>
        <p className="mt-1 text-sm text-zinc-500">
          Your timezone is used for dates in the UI and emails.
        </p>

        <form onSubmit={saveProfile} className="mt-4 space-y-4">
          {profileError && <Alert tone="error">{profileError}</Alert>}
          {profileStatus && <Alert tone="success">{profileStatus}</Alert>}

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="block text-sm font-medium">
              Name
              <input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
            </label>

            <label className="block text-sm font-medium">
              Email
              <input
                required
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
            </label>

            <label className="block text-sm font-medium">
              Timezone
              <input
                required
                list="timezones"
                value={timezone}
                onChange={(e) => setTimezone(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
              <datalist id="timezones">
                {COMMON_TIMEZONES.map((zone) => (
                  <option key={zone} value={zone} />
                ))}
              </datalist>
            </label>
          </div>

          <button
            type="submit"
            disabled={profileBusy}
            className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
          >
            {profileBusy ? "Saving…" : "Save profile"}
          </button>
        </form>
      </section>

      <section className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
        <h2 className="font-semibold">Password</h2>

        <form onSubmit={changePassword} className="mt-4 space-y-4">
          {passwordError && <Alert tone="error">{passwordError}</Alert>}
          {passwordStatus && <Alert tone="success">{passwordStatus}</Alert>}

          <div className="grid gap-4 sm:grid-cols-3">
            <label className="block text-sm font-medium">
              Current password
              <input
                required
                type="password"
                autoComplete="current-password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
            </label>

            <label className="block text-sm font-medium">
              New password
              <input
                required
                type="password"
                autoComplete="new-password"
                minLength={10}
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
            </label>

            <label className="block text-sm font-medium">
              Confirm new password
              <input
                required
                type="password"
                autoComplete="new-password"
                minLength={10}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
              />
            </label>
          </div>

          <button
            type="submit"
            disabled={passwordBusy}
            className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] disabled:opacity-60 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            {passwordBusy ? "Changing…" : "Change password"}
          </button>
        </form>
      </section>

      <section className="rounded-xl border border-red-200 p-6 dark:border-red-900">
        <h2 className="font-semibold text-red-700 dark:text-red-400">Danger zone</h2>
        <p className="mt-1 text-sm text-zinc-500">
          Deleting your account removes your monitors, checks, incidents, channels and status
          pages. This cannot be undone.
        </p>

        <form onSubmit={deleteAccount} className="mt-4 flex flex-wrap items-end gap-3">
          <label className="block text-sm font-medium">
            Confirm with your password
            <input
              required
              type="password"
              autoComplete="current-password"
              value={deletePassword}
              onChange={(e) => setDeletePassword(e.target.value)}
              className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
            />
          </label>
          <button
            type="submit"
            disabled={deleteBusy}
            className="rounded-full bg-red-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-60"
          >
            {deleteBusy ? "Deleting…" : "Delete account"}
          </button>
        </form>

        {deleteError && (
          <div className="mt-3">
            <Alert tone="error">{deleteError}</Alert>
          </div>
        )}
      </section>
    </div>
  );
}

export default function SettingsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();

  useEffect(() => {
    if (!loading && !user) router.push("/login");
  }, [loading, user, router]);

  if (loading || !user) {
    return <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>;
  }

  return (
    <div className="mx-auto w-full max-w-3xl flex-1 px-6 py-8">
      <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Settings</h1>
          <p className="text-sm text-zinc-500">{user.email}</p>
        </div>
        <Link
          href="/dashboard"
          className="rounded-full border border-black/[.08] px-4 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          ← Dashboard
        </Link>
      </header>

      <SettingsInner user={user} />
    </div>
  );
}

"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function LoginPage() {
  const router = useRouter();
  const { signIn } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setBusy(true);
    try {
      await signIn(email, password);
      router.push("/dashboard");
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-1 items-center justify-center px-6">
      <form
        onSubmit={onSubmit}
        className="w-full max-w-sm rounded-xl border border-black/[.08] bg-white p-8 shadow-sm dark:border-white/[.145] dark:bg-black"
      >
        <h1 className="text-2xl font-semibold">Welcome back</h1>
        <p className="mt-1 mb-6 text-sm text-zinc-500">
          Sign in to your PupWatch account.
        </p>

        {error && (
          <div role="alert" className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
            {error}
          </div>
        )}

        <label className="block text-sm font-medium">
          Email
          <input
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.email && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.email[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium">
          Password
          <input
            type="password"
            required
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
        </label>

        <button
          type="submit"
          disabled={busy}
          className="mt-6 w-full rounded-full bg-foreground py-2.5 font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
        >
          {busy ? "Signing in…" : "Sign in"}
        </button>

        <p className="mt-4 text-center text-sm text-zinc-500">
          No account?{" "}
          <Link href="/register" className="font-medium underline">
            Sign up
          </Link>
        </p>
      </form>
    </div>
  );
}
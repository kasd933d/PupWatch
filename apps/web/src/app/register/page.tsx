"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function RegisterPage() {
  const router = useRouter();
  const { signUp } = useAuth();
  const [name, setName] = useState("");
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
      await signUp(name, email, password);
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
        <h1 className="text-2xl font-semibold">Create your account</h1>
        <p className="mt-1 mb-6 text-sm text-zinc-500">
          Free while it lasts — up to 10 monitors.
        </p>

        {error && (
          <div className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
            {error}
          </div>
        )}

        <label className="block text-sm font-medium">
          Name
          <input
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.name && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.name[0]}
            </span>
          )}
        </label>

        <label className="mt-4 block text-sm font-medium">
          Email
          <input
            type="email"
            required
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
          Password (10+ characters)
          <input
            type="password"
            required
            minLength={10}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.password && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.password[0]}
            </span>
          )}
        </label>

        <button
          type="submit"
          disabled={busy}
          className="mt-6 w-full rounded-full bg-foreground py-2.5 font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
        >
          {busy ? "Creating…" : "Sign up"}
        </button>

        <p className="mt-4 text-center text-sm text-zinc-500">
          Already have an account?{" "}
          <Link href="/login" className="font-medium underline">
            Sign in
          </Link>
        </p>
      </form>
    </div>
  );
}
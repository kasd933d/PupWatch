"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { MonitorForm } from "@/components/monitor-form";
import { useAuth } from "@/lib/auth";

export default function NewMonitorPage() {
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
      <header className="mb-6">
        <h1 className="text-2xl font-semibold">New monitor</h1>
        <p className="text-sm text-zinc-500">
          PupWatch will call this URL on a schedule and alert you when it stops working.
        </p>
      </header>

      <MonitorForm
        onSaved={(monitor) => router.push(`/monitors/${monitor.id}`)}
        onCancel={() => router.push("/dashboard")}
      />

      <div className="mt-6">
        <Link href="/dashboard" className="text-sm font-medium underline">
          ← Back to dashboard
        </Link>
      </div>
    </div>
  );
}

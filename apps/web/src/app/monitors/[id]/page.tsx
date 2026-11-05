import { Suspense } from "react";
import { MonitorDetailClient } from "./monitor-detail-client";

// Per-monitor and auth-gated: it must not be prerendered (Next 16 cacheComponents).
export const instant = false;

export default function MonitorDetailPage() {
  return (
    <Suspense
      fallback={
        <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>
      }
    >
      <MonitorDetailClient />
    </Suspense>
  );
}

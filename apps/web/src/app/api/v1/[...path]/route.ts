import { cookies } from "next/headers";
import { NextResponse, type NextRequest } from "next/server";
import { API_BASE_URL, SESSION_COOKIE, clearSessionCookie, readSessionToken } from "@/lib/session";


/**
 * Authenticated proxy for the Laravel API (PRD §4.1). Every browser request to
 * `/api/v1/*` is forwarded with the bearer token taken from the httpOnly cookie,
 * so no credentials ever live in JavaScript.
 */
async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  const token = readSessionToken((await cookies()).get(SESSION_COOKIE)?.value);

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const target = new URL(request.url);
  const upstreamUrl = `${API_BASE_URL}/api/v1/${path.join("/")}${target.search}`;

  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  };

  let body: string | undefined;
  if (!["GET", "HEAD"].includes(request.method)) {
    body = await request.text();
    if (body) headers["Content-Type"] = request.headers.get("content-type") ?? "application/json";
  }

  const response = await fetch(upstreamUrl, {
    method: request.method,
    headers,
    body: body || undefined,
    cache: "no-store",
  });

  const text = await response.text();

  const next = new NextResponse(text || null, {
    status: response.status,
    headers: { "Content-Type": response.headers.get("content-type") ?? "application/json" },
  });

  if (response.status === 401) clearSessionCookie(next);

  return next;
}

export const GET = proxy;
export const POST = proxy;
export const PATCH = proxy;
export const PUT = proxy;
export const DELETE = proxy;

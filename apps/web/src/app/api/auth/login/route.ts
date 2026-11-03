import { NextResponse } from "next/server";
import { API_BASE_URL, setSessionCookie } from "@/lib/session";


/**
 * POST /api/auth/login — proxies to Laravel and keeps the returned token in an
 * httpOnly cookie. The browser only ever receives the user object.
 */
export async function POST(request: Request): Promise<NextResponse> {
  const body = await request.text();

  const response = await fetch(`${API_BASE_URL}/api/v1/auth/login`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body,
    cache: "no-store",
  });

  const data = (await response.json().catch(() => ({}))) as {
    token?: string;
    user?: unknown;
    message?: string;
    errors?: Record<string, string[]>;
  };

  if (!response.ok) {
    return NextResponse.json(
      { message: data.message ?? "Sign in failed.", errors: data.errors },
      { status: response.status },
    );
  }

  const next = NextResponse.json({ user: data.user });
  if (data.token) setSessionCookie(next, data.token);

  return next;
}

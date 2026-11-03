import { NextResponse } from "next/server";
import { API_BASE_URL, setSessionCookie } from "@/lib/session";


/** POST /api/auth/register — creates the account and starts a cookie session. */
export async function POST(request: Request): Promise<NextResponse> {
  const body = await request.text();

  const response = await fetch(`${API_BASE_URL}/api/v1/auth/register`, {
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
      { message: data.message ?? "Could not create the account.", errors: data.errors },
      { status: response.status },
    );
  }

  const next = NextResponse.json({ user: data.user }, { status: 201 });
  if (data.token) setSessionCookie(next, data.token);

  return next;
}

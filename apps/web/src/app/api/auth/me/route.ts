import { cookies } from "next/headers";
import { NextResponse, type NextRequest } from "next/server";
import { API_BASE_URL, SESSION_COOKIE, clearSessionCookie, readSessionToken } from "@/lib/session";

/** GET /api/auth/me — returns the signed-in user, or 401. */
export async function GET(): Promise<NextResponse> {
  const token = readSessionToken((await cookies()).get(SESSION_COOKIE)?.value);

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const response = await fetch(`${API_BASE_URL}/api/v1/me`, {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    cache: "no-store",
  });

  if (!response.ok) {
    const unauthorized = NextResponse.json(
      { message: "Unauthenticated." },
      { status: response.status === 401 ? 401 : 502 },
    );
    if (response.status === 401) clearSessionCookie(unauthorized);
    return unauthorized;
  }

  const data = (await response.json()) as { user?: unknown };

  return NextResponse.json({ user: data.user });
}

/**
 * PATCH /api/auth/me and DELETE /api/auth/me forward to the API with the
 * cookie token, so profile edits and account deletion keep the same rules as
 * every other authenticated request (PRD §4.10).
 */
async function forward(request: NextRequest, method: "PATCH" | "DELETE"): Promise<NextResponse> {
  const token = readSessionToken((await cookies()).get(SESSION_COOKIE)?.value);

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = await request.text();

  const response = await fetch(`${API_BASE_URL}/api/v1/me`, {
    method,
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(body ? { "Content-Type": "application/json" } : {}),
    },
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

export async function PATCH(request: NextRequest): Promise<NextResponse> {
  return forward(request, "PATCH");
}

export async function DELETE(request: NextRequest): Promise<NextResponse> {
  return forward(request, "DELETE");
}

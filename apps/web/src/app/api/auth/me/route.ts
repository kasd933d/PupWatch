import { cookies } from "next/headers";
import { NextResponse } from "next/server";
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

import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import {
  API_BASE_URL,
  SESSION_COOKIE,
  clearSessionCookie,
  readSessionToken,
} from "@/lib/session";


/** POST /api/auth/logout — revokes the token server-side and clears the cookie. */
export async function POST(): Promise<NextResponse> {
  const token = readSessionToken((await cookies()).get(SESSION_COOKIE)?.value);

  if (token) {
    await fetch(`${API_BASE_URL}/api/v1/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    }).catch(() => undefined);
  }

  const next = NextResponse.json({ message: "Signed out." });
  clearSessionCookie(next);

  return next;
}

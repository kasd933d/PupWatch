import type { NextResponse } from "next/server";

/**
 * The Sanctum token lives in an httpOnly cookie set by Next.js route handlers
 * (PRD §4.1) so it is never reachable from browser JavaScript.
 */
export const SESSION_COOKIE = "pupwatch_token";

/** Server-side origin of the Laravel API. Never exposed to the browser. */
export const API_BASE_URL = process.env.API_BASE_URL ?? "http://localhost:8000";

const THIRTY_DAYS = 60 * 60 * 24 * 30;

function cookieOptions(maxAge: number) {
  return {
    httpOnly: true,
    sameSite: "lax" as const,
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge,
  };
}

export function setSessionCookie(response: NextResponse, token: string): void {
  // Next.js encodes the value when serialising Set-Cookie, so store it raw here.
  response.cookies.set(SESSION_COOKIE, token, cookieOptions(THIRTY_DAYS));
}

export function clearSessionCookie(response: NextResponse): void {
  response.cookies.set(SESSION_COOKIE, "", cookieOptions(0));
}

export function readSessionToken(cookieValue: string | undefined): string | null {
  if (!cookieValue) return null;

  try {
    return decodeURIComponent(cookieValue);
  } catch {
    return null;
  }
}

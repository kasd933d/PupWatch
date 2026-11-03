"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { api, ApiRequestError } from "./api";
import type { User } from "./types";

interface AuthState {
  user: User | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (name: string, email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

/**
 * Session state for the browser. The token itself lives in an httpOnly cookie
 * set by the Next.js route handlers, so this provider only ever sees the user.
 */
export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    api
      .me()
      .then((res) => {
        if (!cancelled) setUser(res.user);
      })
      .catch((err: unknown) => {
        if (!cancelled) setUser(null);
        if (!(err instanceof ApiRequestError && err.status === 401)) {
          // A network/API failure is not a session problem; the pages surface it.
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const res = await api.login({ email, password });
    setUser(res.user);
  }, []);

  const signUp = useCallback(async (name: string, email: string, password: string) => {
    const res = await api.register({
      name,
      email,
      password,
      password_confirmation: password,
    });
    setUser(res.user);
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api.logout();
    } finally {
      setUser(null);
    }
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, signIn, signUp, signOut }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}

import axios from 'axios';

function getApiBaseUrl(): string {
  if (typeof window !== 'undefined') return '/api/backend';

  return process.env.INTERNAL_API_URL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
}

export const apiHttpClient = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 20_000,
  withCredentials: typeof window !== 'undefined',
  headers: {
    Accept: 'application/json',
  },
});

export const sessionHttpClient = axios.create({
  timeout: 5_000,
  withCredentials: true,
});

export class ApiRequestError extends Error {
  readonly status: number | undefined;
  readonly code: string | undefined;

  constructor(message: string, status?: number, code?: string) {
    super(message);
    this.name = 'ApiRequestError';
    this.status = status;
    this.code = code;
  }
}

export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim()) return error.message;
  return fallback;
}

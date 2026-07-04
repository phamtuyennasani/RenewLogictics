import { mockBootstrap, mockCreateShippingRequest, mockQuote } from "../mock";
import type {
  BootstrapData,
  CountryOption,
  CreatedShippingRequest,
  QuoteForm,
  QuoteResult,
  ShippingRequestPayload,
} from "../types";

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? "").replace(/\/$/, "");

type Envelope<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: unknown;
};

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  if (!apiBaseUrl) {
    throw new Error("API base URL is not configured.");
  }

  const response = await fetch(`${apiBaseUrl}${path}`, {
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(init?.headers ?? {}),
    },
    ...init,
  });
  const payload = (await response.json()) as Envelope<T>;

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || "Không thể kết nối máy chủ.");
  }

  return (payload.data ?? payload) as T;
}

export async function fetchBootstrap(): Promise<BootstrapData> {
  if (!apiBaseUrl) {
    return mockBootstrap;
  }

  return request<BootstrapData>("/api/zalo-mini-app/bootstrap");
}

export async function fetchCountries(serviceId?: string): Promise<CountryOption[]> {
  if (!apiBaseUrl) {
    return mockBootstrap.countries;
  }

  const query = serviceId ? `?service_id=${encodeURIComponent(serviceId)}` : "";
  const response = await request<{ items: CountryOption[] }>(`/api/zalo-mini-app/countries${query}`);

  return response.items;
}

export async function calculateQuote(form: QuoteForm): Promise<QuoteResult> {
  if (!apiBaseUrl) {
    return mockQuote(form);
  }

  return request<QuoteResult>("/api/zalo-mini-app/quote", {
    method: "POST",
    body: JSON.stringify({
      service_id: Number(form.service_id),
      country_id: Number(form.country_id),
      g_weight: Number(form.g_weight),
      length: form.length ? Number(form.length) : undefined,
      width: form.width ? Number(form.width) : undefined,
      height: form.height ? Number(form.height) : undefined,
    }),
  });
}

export async function createShippingRequest(
  payload: ShippingRequestPayload,
): Promise<CreatedShippingRequest> {
  if (!apiBaseUrl) {
    return mockCreateShippingRequest(payload);
  }

  return request<CreatedShippingRequest>("/api/zalo-mini-app/shipping-requests", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

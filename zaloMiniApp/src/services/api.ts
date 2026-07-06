import {
  mockBootstrap,
  mockCreateOrder,
  mockCreateShippingRequest,
  mockFetchOrder,
  mockFetchOrders,
  mockFetchPriceList,
  mockFetchPriceLists,
  mockLogin,
  mockOrderFormBootstrap,
  mockPriceBootstrap,
  mockQuote,
  mockSavePriceList,
  mockTracking,
} from "../mock";
import type {
  AuthPayload,
  BootstrapData,
  CountryOption,
  CreateOrderPayload,
  CreateOrderResult,
  CreatedShippingRequest,
  LoginPayload,
  OrderDetail,
  OrderFormBootstrap,
  OrderListResult,
  PriceListBootstrap,
  PriceListDetail,
  PriceListDetailResult,
  PriceListListResult,
  PriceListPayload,
  QuoteForm,
  QuoteResult,
  ShippingRequestPayload,
  TrackingResult,
} from "../types";
import { getStoredItem, removeStoredItem, setStoredItem } from "./zalo";

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? "").replace(/\/$/, "");
const tokenStorageKey = "hethong_zalo_mini_app_token";

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
  const payload = (await response.json().catch(() => ({}))) as Envelope<T>;

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || "Không thể kết nối máy chủ.");
  }

  return (payload.data ?? payload) as T;
}

async function authRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const token = getAuthToken();

  if (!token) {
    throw new Error("Vui lòng đăng nhập.");
  }

  try {
    return await request<T>(path, {
      ...init,
      headers: {
        Authorization: `Bearer ${token}`,
        ...(init?.headers ?? {}),
      },
    });
  } catch (error) {
    if (error instanceof Error && /hết hạn|đăng nhập/i.test(error.message)) {
      clearAuthToken();
    }
    throw error;
  }
}

export function getAuthToken(): string | null {
  return getStoredItem(tokenStorageKey);
}

export function setAuthToken(token: string): void {
  setStoredItem(tokenStorageKey, token);
}

export function clearAuthToken(): void {
  removeStoredItem(tokenStorageKey);
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

export async function login(payload: LoginPayload): Promise<AuthPayload> {
  if (!apiBaseUrl) {
    const auth = mockLogin(payload);
    if (auth.token) {
      setAuthToken(auth.token);
    }
    return auth;
  }

  const auth = await request<AuthPayload>("/api/zalo-mini-app/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (auth.token) {
    setAuthToken(auth.token);
  }

  return auth;
}

export async function loginWithZalo(zaloAccessToken: string): Promise<AuthPayload> {
  if (!apiBaseUrl) {
    const auth = mockLogin({
      username: "zalo-user",
      password: "",
      device_name: "zalo-mini-app",
      link_zalo: true,
      zalo_access_token: zaloAccessToken,
    });
    if (auth.token) {
      setAuthToken(auth.token);
    }
    return auth;
  }

  const auth = await request<AuthPayload>("/api/zalo-mini-app/auth/zalo", {
    method: "POST",
    body: JSON.stringify({
      device_name: "zalo-mini-app",
      zalo_access_token: zaloAccessToken,
    }),
  });

  if (auth.token) {
    setAuthToken(auth.token);
  }

  return auth;
}

export async function fetchMe(): Promise<AuthPayload> {
  if (!apiBaseUrl) {
    return mockLogin({ username: "demo", password: "secret" });
  }

  return authRequest<AuthPayload>("/api/zalo-mini-app/me");
}

export async function logout(): Promise<void> {
  if (apiBaseUrl && getAuthToken()) {
    await authRequest<null>("/api/zalo-mini-app/auth/logout", { method: "POST" }).catch(() => null);
  }

  clearAuthToken();
}

export async function linkZaloAccount(zaloAccessToken: string): Promise<AuthPayload> {
  return authRequest<AuthPayload>("/api/zalo-mini-app/auth/zalo-link", {
    method: "POST",
    body: JSON.stringify({ zalo_access_token: zaloAccessToken }),
  });
}

export async function fetchTracking(code: string): Promise<TrackingResult> {
  if (!apiBaseUrl) {
    return mockTracking(code);
  }

  return request<TrackingResult>(`/api/zalo-mini-app/tracking/${encodeURIComponent(code)}`);
}

export async function fetchOrderFormBootstrap(): Promise<OrderFormBootstrap> {
  if (!apiBaseUrl) {
    return mockOrderFormBootstrap;
  }

  return authRequest<OrderFormBootstrap>("/api/zalo-mini-app/order-form/bootstrap");
}

export async function fetchOrders(params: {
  search?: string;
  status?: string;
  page?: number;
  per_page?: number;
} = {}): Promise<OrderListResult> {
  if (!apiBaseUrl) {
    return mockFetchOrders();
  }

  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value).trim() !== "") {
      query.set(key, String(value));
    }
  });

  return authRequest<OrderListResult>(`/api/zalo-mini-app/orders${query.size ? `?${query}` : ""}`);
}

export async function fetchOrder(orderId: number): Promise<OrderDetail> {
  if (!apiBaseUrl) {
    return mockFetchOrder(orderId);
  }

  return authRequest<OrderDetail>(`/api/zalo-mini-app/orders/${orderId}`);
}

export async function createOrder(payload: CreateOrderPayload): Promise<CreateOrderResult> {
  if (!apiBaseUrl) {
    return mockCreateOrder(payload);
  }

  return authRequest<CreateOrderResult>("/api/zalo-mini-app/orders", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function fetchPriceBootstrap(): Promise<PriceListBootstrap> {
  if (!apiBaseUrl) {
    return mockPriceBootstrap;
  }

  return authRequest<PriceListBootstrap>("/api/zalo-mini-app/price-lists/bootstrap");
}

export async function fetchPriceLists(params: {
  search?: string;
  page?: number;
  per_page?: number;
} = {}): Promise<PriceListListResult> {
  if (!apiBaseUrl) {
    return mockFetchPriceLists();
  }

  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value).trim() !== "") {
      query.set(key, String(value));
    }
  });

  return authRequest<PriceListListResult>(`/api/zalo-mini-app/price-lists${query.size ? `?${query}` : ""}`);
}

export async function fetchPriceList(id: number): Promise<PriceListDetailResult> {
  if (!apiBaseUrl) {
    return mockFetchPriceList(id);
  }

  return authRequest<PriceListDetailResult>(`/api/zalo-mini-app/price-lists/${id}`);
}

export async function savePriceList(
  payload: PriceListPayload,
  id?: number,
): Promise<PriceListDetailResult> {
  if (!apiBaseUrl) {
    return mockSavePriceList(payload, id);
  }

  return authRequest<PriceListDetailResult>(id ? `/api/zalo-mini-app/price-lists/${id}` : "/api/zalo-mini-app/price-lists", {
    method: id ? "PUT" : "POST",
    body: JSON.stringify(payload),
  });
}

export async function savePriceListDetails(
  id: number,
  details: PriceListDetail[],
): Promise<PriceListDetailResult> {
  if (!apiBaseUrl) {
    const current = mockFetchPriceList(id);
    return mockSavePriceList(
      {
        name: current.name,
        service_id: current.service.id,
        country_ids: current.countries.map((country) => country.id),
        details,
      },
      id,
    );
  }

  return authRequest<PriceListDetailResult>(`/api/zalo-mini-app/price-lists/${id}/details`, {
    method: "PUT",
    body: JSON.stringify({ details }),
  });
}

export async function deletePriceList(id: number): Promise<void> {
  if (!apiBaseUrl) {
    return;
  }

  await authRequest<null>(`/api/zalo-mini-app/price-lists/${id}`, { method: "DELETE" });
}

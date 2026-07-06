import type {
  AuthPayload,
  BootstrapData,
  CreatedShippingRequest,
  CreateOrderPayload,
  CreateOrderResult,
  LoginPayload,
  OrderDetail,
  OrderFormBootstrap,
  OrderListResult,
  PriceListBootstrap,
  PriceListDetailResult,
  PriceListListResult,
  PriceListPayload,
  QuoteForm,
  QuoteResult,
  ShippingRequestPayload,
  TrackingResult,
} from "./types";

export const mockBootstrap: BootstrapData = {
  dim: 6000,
  services: [
    { id: 1, name: "Air Express" },
    { id: 2, name: "Tiết kiệm quốc tế" },
    { id: 3, name: "Hàng thương mại" },
  ],
  countries: [
    { id: 840, name: "United States" },
    { id: 392, name: "Japan" },
    { id: 410, name: "South Korea" },
    { id: 36, name: "Australia" },
  ],
};

export function mockQuote(form: QuoteForm): QuoteResult {
  const weight = Number(form.g_weight || 0);
  const length = Number(form.length || 0);
  const width = Number(form.width || 0);
  const height = Number(form.height || 0);
  const volumetric = length && width && height ? (length * width * height) / mockBootstrap.dim : 0;
  const chargeableBase = Math.max(weight, volumetric);
  const chargeableWeight =
    chargeableBase < 21 ? Math.ceil(chargeableBase / 0.5) * 0.5 : Math.ceil(chargeableBase);
  const unitPrice = Number(form.service_id) === 2 ? 118000 : 156000;

  return {
    sale_price: Math.round(chargeableWeight * unitPrice),
    chargeable_weight: Number(chargeableWeight.toFixed(2)),
    quycach: "DON_GIA",
    unit_price: unitPrice,
  };
}

export function mockCreateShippingRequest(
  payload: ShippingRequestPayload,
): CreatedShippingRequest {
  return {
    id: Date.now(),
    code: `ZMA${String(Date.now()).slice(-6)}`,
    status: payload.phone ? "new" : "draft",
  };
}

export function mockLogin(payload: LoginPayload): AuthPayload {
  const isAdmin = payload.username.toLowerCase().includes("admin");

  return {
    token: `mock-token-${Date.now()}`,
    user: {
      id: 1,
      username: payload.username,
      fullname: isAdmin ? "Quản trị hệ thống" : "Nhân viên CS",
      phone: "0900000000",
      zalo_linked: Boolean(payload.link_zalo),
    },
    roles: [isAdmin ? "admin" : "cs"],
    abilities: {
      orders_view: true,
      orders_create: true,
      prices_manage: isAdmin,
      prices_delete: isAdmin,
      finance_view: isAdmin,
      orders_scope: isAdmin ? "all" : "assigned_or_unassigned_cs",
    },
  };
}

export function mockTracking(code: string): TrackingResult {
  return {
    id_bill: code.toUpperCase(),
    tracking_code: `TRK-${code.toUpperCase()}`,
    status: {
      value: "dang_phat_hang",
      label: "Đang phát hàng",
      color: "bg-amber-100 text-amber-700",
    },
    chargeable_weight: {
      value: 4.5,
      unit: "kg",
    },
    receiver: {
      name: "N*** V*** A***",
      phone: "*******456",
      destination: "California, United States",
      country: "United States",
    },
    service: {
      main: { id: 1, name: "Air Express" },
      detail: { id: null, name: null },
      shipment_type: { id: null, name: null },
    },
    shipping_history: [
      {
        time: new Date().toISOString(),
        source: "manual",
        source_label: "Lịch sử đơn hàng",
        status: "Đang phát hàng",
        location: "Los Angeles",
        description: "Đơn đang được phát tới người nhận.",
      },
      {
        time: new Date(Date.now() - 86400000).toISOString(),
        source: "manual",
        source_label: "Lịch sử đơn hàng",
        status: "Duyệt xuất hàng",
        location: "TP.HCM",
        description: "Đơn đã rời kho.",
      },
    ],
  };
}

export const mockOrderFormBootstrap: OrderFormBootstrap = {
  ...mockBootstrap,
  statuses: [
    { value: "moi_tao", label: "Mới tạo" },
    { value: "da_xac_nhan", label: "Đã xác nhận" },
    { value: "da_nhan_hang", label: "Đã nhận hàng" },
    { value: "duyet_xuat_hang", label: "Duyệt xuất hàng" },
    { value: "dang_phat_hang", label: "Đang phát hàng" },
    { value: "da_giao", label: "Đã giao" },
  ],
};

const mockOrders: OrderDetail[] = [
  {
    id: 101,
    uuid: "demo-order-101",
    id_bill: "BEE260704001",
    tracking_code: "TRK-DEMO-001",
    status: { value: "dang_phat_hang", label: "Đang phát hàng" },
    created_at: new Date(Date.now() - 86400000).toISOString(),
    updated_at: new Date().toISOString(),
    sender: {
      name: "Nguyễn Minh",
      phone: "0900000001",
      address: "Quận 1, TP.HCM",
    },
    receiver: {
      name: "Anna Lee",
      phone: "0800000002",
      address: "1 Market St",
      destination: "San Francisco, CA, United States",
      country: "United States",
      country_id: 840,
    },
    service: {
      main: { id: 1, name: "Air Express" },
      detail: { id: null, name: null },
      shipment_type: { id: null, name: null },
    },
    package_count: 1,
    chargeable_weight: { value: 4.5, unit: "kg" },
    notes: "Demo order",
    payment: {
      sale_total: 702000,
      cost_total: 520000,
      base_total: 500000,
      profit: 182000,
    },
    packages: [
      {
        id: 1,
        code: "BEE260704001-01",
        length: 30,
        width: 25,
        height: 20,
        g_weight: 4.5,
        v_weight: 2.5,
        c_weight: 4.5,
      },
    ],
    invoice_items: [
      {
        id: 1,
        tenhang: "Sample product",
        soluong: 2,
        price: 20,
        total: 40,
        xuatxu: "VN",
      },
    ],
    shipping_history: mockTracking("BEE260704001").shipping_history,
  },
];

export function mockFetchOrders(): OrderListResult {
  return {
    items: mockOrders,
    meta: {
      current_page: 1,
      per_page: 15,
      total: mockOrders.length,
      last_page: 1,
      has_more: false,
    },
    statuses: mockOrderFormBootstrap.statuses,
  };
}

export function mockFetchOrder(orderId: number): OrderDetail {
  return mockOrders.find((order) => order.id === orderId) ?? mockOrders[0];
}

export function mockCreateOrder(payload: CreateOrderPayload): CreateOrderResult {
  const service = mockBootstrap.services.find((item) => item.id === payload.service_id);
  const country = mockBootstrap.countries.find((item) => item.id === payload.country_id);
  const firstPackage = payload.packages[0];
  const order: OrderDetail = {
    id: Date.now(),
    uuid: `demo-${Date.now()}`,
    id_bill: `BEE${String(Date.now()).slice(-9)}`,
    tracking_code: null,
    status: { value: "moi_tao", label: "Mới tạo" },
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    sender: {
      name: payload.sender.name,
      phone: payload.sender.phone,
      address: payload.sender.address,
    },
    receiver: {
      name: payload.receiver.name,
      phone: payload.receiver.phone,
      address: payload.receiver.address,
      destination: [payload.receiver.city, payload.receiver.state, country?.name, payload.receiver.postcode]
        .filter(Boolean)
        .join(", "),
      country: country?.name,
      country_id: country?.id,
    },
    service: {
      main: { id: service?.id, name: service?.name },
      detail: { id: null, name: null },
      shipment_type: { id: null, name: null },
    },
    package_count: payload.packages.length,
    chargeable_weight: { value: Number(firstPackage?.g_weight ?? 0), unit: "kg" },
    notes: payload.notes,
    packages: payload.packages.map((item, index) => ({
      id: index + 1,
      code: `DEMO-${index + 1}`,
      length: Number(item.length ?? 0),
      width: Number(item.width ?? 0),
      height: Number(item.height ?? 0),
      g_weight: Number(item.g_weight),
      c_weight: Number(item.g_weight),
    })),
    invoice_items: payload.invoice_items ?? [],
    shipping_history: [
      {
        time: new Date().toISOString(),
        source: "manual",
        source_label: "Lịch sử đơn hàng",
        status: "Mới tạo",
        description: "Đơn được tạo từ Mini App.",
      },
    ],
  };

  mockOrders.unshift(order);

  return { order, warnings: [] };
}

const mockPriceLists: PriceListDetailResult[] = [
  {
    id: 1,
    name: "Air Express - US",
    service: { id: 1, name: "Air Express" },
    countries: [{ id: 840, name: "United States", iso2: "US" }],
    details_count: 2,
    updated_at: new Date().toISOString(),
    details: [
      { id: 1, quycach: "DON_GIA", weight_from: 0, weight_to: 5, sale_price: 156000, cost_price: 120000, base_price: 110000 },
      { id: 2, quycach: "DON_GIA", weight_from: 5.01, weight_to: 20, sale_price: 148000, cost_price: 112000, base_price: 102000 },
    ],
  },
];

export const mockPriceBootstrap: PriceListBootstrap = {
  services: mockBootstrap.services,
  countries: mockBootstrap.countries.map((country) => ({ ...country, iso2: country.name.slice(0, 2).toUpperCase() })),
};

export function mockFetchPriceLists(): PriceListListResult {
  return {
    items: mockPriceLists,
    meta: {
      current_page: 1,
      per_page: 15,
      total: mockPriceLists.length,
      last_page: 1,
      has_more: false,
    },
  };
}

export function mockFetchPriceList(id: number): PriceListDetailResult {
  return mockPriceLists.find((list) => list.id === id) ?? mockPriceLists[0];
}

export function mockSavePriceList(payload: PriceListPayload, id?: number): PriceListDetailResult {
  const service = mockBootstrap.services.find((item) => item.id === payload.service_id);
  const countries = mockBootstrap.countries.filter((item) => payload.country_ids.includes(item.id));
  const saved: PriceListDetailResult = {
    id: id ?? Date.now(),
    name: payload.name,
    service: { id: payload.service_id, name: service?.name },
    countries,
    details_count: payload.details?.length ?? 0,
    updated_at: new Date().toISOString(),
    details: payload.details ?? [],
  };
  const index = mockPriceLists.findIndex((item) => item.id === saved.id);
  if (index >= 0) {
    mockPriceLists[index] = saved;
  } else {
    mockPriceLists.unshift(saved);
  }
  return saved;
}

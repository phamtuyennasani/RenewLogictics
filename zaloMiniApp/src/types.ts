export type ServiceOption = {
  id: number;
  name: string;
};

export type CountryOption = {
  id: number;
  name: string;
};

export type QuoteForm = {
  service_id: string;
  country_id: string;
  g_weight: string;
  length: string;
  width: string;
  height: string;
};

export type QuoteResult = {
  sale_price: number;
  chargeable_weight: number;
  quycach: string;
  unit_price: number;
};

export type ShippingRequestForm = {
  requester_name: string;
  phone: string;
  email: string;
  company: string;
  pickup_address: string;
  pickup_city: string;
  note: string;
  package_count: string;
};

export type ShippingRequestPayload = {
  requester_name: string;
  phone: string;
  email?: string;
  company?: string;
  pickup_address: string;
  pickup_city?: string;
  receiver_country_id?: number;
  receiver_country?: string;
  service_id?: number;
  service_name?: string;
  package_count: number;
  weight_kg: number;
  length_cm?: number;
  width_cm?: number;
  height_cm?: number;
  note?: string;
  quote_snapshot?: QuoteResult;
  zalo_access_token?: string;
};

export type BootstrapData = {
  services: ServiceOption[];
  countries: CountryOption[];
  dim: number;
};

export type StatusOption = {
  value: string;
  label: string;
  color?: string;
};

export type CreatedShippingRequest = {
  id: number;
  code: string;
  status: string;
};

export type ZaloMiniAppAbilities = {
  orders_view: boolean;
  orders_create: boolean;
  prices_manage: boolean;
  prices_delete: boolean;
  finance_view: boolean;
  orders_scope: "all" | "sale" | "ctv" | "assigned_or_unassigned_cs" | "assigned_or_unassigned_ops" | "none";
};

export type AuthUser = {
  id: number;
  username: string;
  code?: string | null;
  fullname?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  avatar?: string | null;
  zalo_linked?: boolean;
};

export type AuthPayload = {
  token?: string;
  user: AuthUser;
  roles: string[];
  abilities: ZaloMiniAppAbilities;
};

export type LoginPayload = {
  username: string;
  password: string;
  device_name?: string;
  link_zalo?: boolean;
  zalo_access_token?: string;
};

export type TrackingStatus = {
  value: string;
  label: string;
  color?: string;
};

export type TrackingHistoryItem = {
  time?: string | null;
  source?: string;
  source_label?: string;
  status?: string | null;
  location?: string | null;
  description?: string | null;
  tracking_number?: string;
  courier_code?: string;
  package_label?: string;
};

export type TrackingResult = {
  id_bill: string;
  tracking_code?: string | null;
  status?: TrackingStatus | null;
  chargeable_weight: {
    value: number;
    unit: string;
  };
  receiver: {
    name: string;
    phone: string;
    destination: string;
    country?: string | null;
    country_id?: number | string | null;
  };
  shipping_history: TrackingHistoryItem[];
  service: {
    main: { id?: number | string | null; name?: string | null };
    detail: { id?: number | string | null; name?: string | null };
    shipment_type: { id?: number | string | null; name?: string | null };
  };
};

export type PageMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
};

export type OrderPayment = {
  sale_total: number;
  cost_total: number;
  base_total: number;
  profit: number;
};

export type OrderPackage = {
  id?: number;
  code?: string | null;
  length: number;
  width: number;
  height: number;
  g_weight: number;
  v_weight?: number;
  c_weight?: number;
  package_type?: string | null;
};

export type OrderInvoiceItem = {
  id?: number;
  tenhang: string;
  soluong: number;
  xuatxu?: string | null;
  loaihang?: string | null;
  hscode?: string | null;
  price: number;
  total?: number;
};

export type OrderSummary = {
  id: number;
  uuid?: string | null;
  id_bill: string;
  tracking_code?: string | null;
  status?: TrackingStatus | null;
  created_at?: string | null;
  updated_at?: string | null;
  sender: {
    name: string;
    phone: string;
    address: string;
  };
  receiver: {
    name: string;
    phone: string;
    address: string;
    destination: string;
    country?: string | null;
    country_id?: number | string | null;
  };
  service: TrackingResult["service"];
  package_count: number;
  chargeable_weight: {
    value: number;
    unit: string;
  };
  notes?: string | null;
  payment?: OrderPayment;
};

export type OrderDetail = OrderSummary & {
  packages: OrderPackage[];
  invoice_items: OrderInvoiceItem[];
  shipping_history: TrackingHistoryItem[];
};

export type OrderListResult = {
  items: OrderSummary[];
  meta: PageMeta;
  statuses: StatusOption[];
};

export type OrderFormBootstrap = BootstrapData & {
  statuses: StatusOption[];
};

export type CreateOrderPayload = {
  service_id: number;
  country_id: number;
  id_sale?: number;
  id_customer?: number;
  sender: {
    name: string;
    phone: string;
    email?: string;
    company?: string;
    address: string;
  };
  receiver: {
    name: string;
    phone: string;
    email?: string;
    company?: string;
    address: string;
    state?: string;
    city?: string;
    postcode?: string;
  };
  packages: Array<{
    number_of_package?: number;
    length?: number;
    width?: number;
    height?: number;
    g_weight: number;
    package_type?: string;
  }>;
  invoice_items?: OrderInvoiceItem[];
  notes?: string;
};

export type CreateOrderResult = {
  order: OrderDetail;
  warnings: string[];
};

export type PriceListDetail = {
  id?: number;
  quycach: "CO_DINH" | "DON_GIA";
  weight_from: number;
  weight_to: number;
  sale_price: number;
  cost_price: number;
  base_price: number;
};

export type PriceListItem = {
  id: number;
  name: string;
  service: {
    id: number;
    name?: string | null;
  };
  countries: Array<CountryOption & { iso2?: string | null }>;
  details_count: number;
  updated_at?: string | null;
};

export type PriceListDetailResult = PriceListItem & {
  details: PriceListDetail[];
};

export type PriceListListResult = {
  items: PriceListItem[];
  meta: PageMeta;
};

export type PriceListBootstrap = {
  services: ServiceOption[];
  countries: Array<CountryOption & { iso2?: string | null }>;
};

export type PriceListPayload = {
  name: string;
  service_id: number;
  country_ids: number[];
  details?: PriceListDetail[];
};

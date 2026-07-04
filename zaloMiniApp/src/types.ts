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
  zalo_user_id?: string;
};

export type BootstrapData = {
  services: ServiceOption[];
  countries: CountryOption[];
  dim: number;
};

export type CreatedShippingRequest = {
  id: number;
  code: string;
  status: string;
};

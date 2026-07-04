import type {
  BootstrapData,
  CreatedShippingRequest,
  QuoteForm,
  QuoteResult,
  ShippingRequestPayload,
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

import { useEffect, useMemo, useState } from "react";
import type { ButtonHTMLAttributes, FormEvent, HTMLInputTypeAttribute, InputHTMLAttributes, ReactNode } from "react";
import { Button, Icon, Input, Select, Text } from "zmp-ui";
import {
  calculateQuote,
  clearAuthToken,
  createOrder,
  createShippingRequest,
  deletePriceList,
  fetchBootstrap,
  fetchCountries,
  fetchMe,
  fetchOrder,
  fetchOrderFormBootstrap,
  fetchOrders,
  fetchPriceBootstrap,
  fetchPriceList,
  fetchPriceLists,
  fetchTracking,
  getAuthToken,
  login,
  loginWithZalo,
  logout,
  savePriceList,
} from "./services/api";
import { getZaloLoginContext } from "./services/zalo";
import type { ZaloUserInfo } from "./services/zalo";
import type {
  AuthPayload,
  BootstrapData,
  CountryOption,
  CreateOrderPayload,
  OrderDetail,
  OrderFormBootstrap,
  OrderInvoiceItem,
  OrderListResult,
  OrderSummary,
  PriceListBootstrap,
  PriceListDetail,
  PriceListDetailResult,
  PriceListItem,
  QuoteForm,
  QuoteResult,
  ServiceOption,
  ShippingRequestForm,
  StatusOption,
  TrackingResult,
} from "./types";

type ScreenKey = "dashboard" | "quote" | "pickup" | "tracking" | "orders" | "order-create" | "prices" | "account";

type SelectOption = {
  value: string;
  label: string;
};

type OrderFormState = {
  service_id: string;
  country_id: string;
  sender_name: string;
  sender_phone: string;
  sender_email: string;
  sender_company: string;
  sender_address: string;
  receiver_name: string;
  receiver_phone: string;
  receiver_email: string;
  receiver_company: string;
  receiver_address: string;
  receiver_city: string;
  receiver_state: string;
  receiver_postcode: string;
  package_count: string;
  g_weight: string;
  length: string;
  width: string;
  height: string;
  invoice_name: string;
  invoice_qty: string;
  invoice_price: string;
  notes: string;
};

type PriceDetailForm = {
  quycach: "CO_DINH" | "DON_GIA";
  weight_from: string;
  weight_to: string;
  sale_price: string;
  cost_price: string;
  base_price: string;
};

type PriceFormState = {
  id?: number;
  name: string;
  service_id: string;
  country_id: string;
  details: PriceDetailForm[];
};

const initialQuoteForm: QuoteForm = {
  service_id: "",
  country_id: "",
  g_weight: "",
  length: "",
  width: "",
  height: "",
};

const initialRequestForm: ShippingRequestForm = {
  requester_name: "",
  phone: "",
  email: "",
  company: "",
  pickup_address: "",
  pickup_city: "",
  note: "",
  package_count: "1",
};

const initialOrderForm: OrderFormState = {
  service_id: "",
  country_id: "",
  sender_name: "",
  sender_phone: "",
  sender_email: "",
  sender_company: "",
  sender_address: "",
  receiver_name: "",
  receiver_phone: "",
  receiver_email: "",
  receiver_company: "",
  receiver_address: "",
  receiver_city: "",
  receiver_state: "",
  receiver_postcode: "",
  package_count: "1",
  g_weight: "",
  length: "",
  width: "",
  height: "",
  invoice_name: "",
  invoice_qty: "1",
  invoice_price: "",
  notes: "",
};

const emptyPriceDetail: PriceDetailForm = {
  quycach: "DON_GIA",
  weight_from: "0",
  weight_to: "5",
  sale_price: "",
  cost_price: "",
  base_price: "",
};

const initialPriceForm: PriceFormState = {
  name: "",
  service_id: "",
  country_id: "",
  details: [{ ...emptyPriceDetail }],
};

const money = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

const timeFormatter = new Intl.DateTimeFormat("vi-VN", {
  hour: "2-digit",
  minute: "2-digit",
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
});

function numberOrZero(value: string): number {
  return value.trim() === "" ? 0 : Number(value);
}

function numericValue(value: string): number | undefined {
  return value.trim() === "" ? undefined : Number(value);
}

function formatTime(value?: string | null): string {
  if (!value) {
    return "-";
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : timeFormatter.format(date);
}

function optionsFrom(items: Array<ServiceOption | CountryOption>): SelectOption[] {
  return items.map((item) => ({ value: String(item.id), label: item.name }));
}

function initials(name?: string | null): string {
  const clean = (name ?? "").trim();
  return clean ? clean.slice(0, 1).toUpperCase() : "B";
}

function ActionButton({
  children,
  tone = "primary",
  loading,
  className = "",
  htmlType = "button",
  icon,
  ...props
}: {
  children: ReactNode;
  tone?: "primary" | "secondary" | "quiet" | "danger";
  loading?: boolean;
  htmlType?: "submit" | "button" | "reset";
  icon?: string;
} & Omit<ButtonHTMLAttributes<HTMLButtonElement>, "type">) {
  const variant = tone === "primary" ? "primary" : tone === "secondary" ? "secondary" : "tertiary";
  const buttonType = tone === "danger" ? "danger" : tone === "quiet" ? "neutral" : "highlight";

  return (
    <Button
      {...props}
      className={`factory-button ${tone} ${className}`.trim()}
      htmlType={htmlType}
      loading={loading}
      type={buttonType}
      variant={variant}
    >
      <span>{children}</span>
      {icon ? (
        <i aria-hidden="true">
          <Icon icon={icon} size={16} />
        </i>
      ) : null}
    </Button>
  );
}

function Field({
  label,
  value,
  onChange,
  type = "text",
  placeholder,
  suffix,
  inputMode,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: HTMLInputTypeAttribute;
  placeholder?: string;
  suffix?: string;
  inputMode?: InputHTMLAttributes<HTMLInputElement>["inputMode"];
}) {
  const inputType = type === "number" || type === "password" ? type : "text";
  const resolvedInputMode = inputMode ?? (type === "email" ? "email" : type === "tel" ? "tel" : undefined);

  return (
    <Input
      clearable
      inputMode={resolvedInputMode}
      label={label}
      placeholder={placeholder}
      size="large"
      suffix={suffix}
      type={inputType}
      value={value}
      onChange={(event) => onChange(event.currentTarget.value)}
    />
  );
}

function TextAreaField({
  label,
  value,
  onChange,
  placeholder,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}) {
  return (
    <Input.TextArea
      label={label}
      placeholder={placeholder}
      size="large"
      value={value}
      onChange={(event) => onChange(event.currentTarget.value)}
    />
  );
}

function SelectField({
  label,
  value,
  onChange,
  placeholder,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder: string;
  options: SelectOption[];
}) {
  return (
    <Select closeOnSelect label={label} placeholder={placeholder} value={value || undefined} onChange={(selected) => onChange(String(selected ?? ""))}>
      {options.map((option) => (
        <Select.Option key={option.value} value={option.value} title={option.label} />
      ))}
    </Select>
  );
}

function Notice({ kind, children }: { kind: "error" | "success"; children: string }) {
  return (
    <div className={`factory-notice ${kind}`} role={kind === "error" ? "alert" : "status"}>
      <Icon icon={kind === "error" ? "zi-warning-circle" : "zi-check-circle"} size={18} />
      <Text size="small">{children}</Text>
    </div>
  );
}

function Panel({ title, subtitle, action, children }: { title?: string; subtitle?: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="factory-panel">
      {title ? (
        <header className="factory-panel-head">
          <div>
            <span>{subtitle}</span>
            <h2>{title}</h2>
          </div>
          {action}
        </header>
      ) : null}
      {children}
    </section>
  );
}

function PageFrame({
  label,
  title,
  text,
  stats,
  children,
}: {
  label: string;
  title: string;
  text: string;
  stats?: Array<{ label: string; value: ReactNode }>;
  children: ReactNode;
}) {
  return (
    <div className="factory-route">
      <section className="factory-page-lead">
        <span>{label}</span>
        <h1>{title}</h1>
        <p>{text}</p>
        {stats ? <MetricStrip items={stats} /> : null}
      </section>
      {children}
    </div>
  );
}

function MetricStrip({ items }: { items: Array<{ label: string; value: ReactNode }> }) {
  return (
    <section className="factory-metric-strip" aria-label="Chỉ số nhanh">
      {items.map((item) => (
        <div key={item.label}>
          <span>{item.label}</span>
          <strong>{item.value}</strong>
        </div>
      ))}
    </section>
  );
}

function StatBlock({ label, value, tone = "base" }: { label: string; value: ReactNode; tone?: "base" | "green" | "gold" | "danger" }) {
  return (
    <div className={`factory-stat ${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function StatusPill({ status }: { status?: StatusOption | null }) {
  return <span className={`factory-status ${status?.value ?? "neutral"}`}>{status?.label ?? "Chưa cập nhật"}</span>;
}

function EmptyState({ title, text, icon = "zi-info-circle" }: { title: string; text: string; icon?: string }) {
  return (
    <div className="factory-empty">
      <Icon icon={icon} size={30} />
      <strong>{title}</strong>
      <p>{text}</p>
    </div>
  );
}

function LoadingRows({ count = 3 }: { count?: number }) {
  return (
    <div className="factory-loading" aria-label="Đang tải">
      {Array.from({ length: count }, (_, index) => (
        <div key={index}>
          <span />
          <span />
          <span />
        </div>
      ))}
    </div>
  );
}

function Timeline({ items }: { items: TrackingResult["shipping_history"] }) {
  if (!items.length) {
    return <EmptyState title="Chưa có hành trình" text="Đơn hàng chưa ghi nhận lịch sử vận chuyển." icon="zi-clock-1" />;
  }

  return (
    <div className="factory-timeline">
      {items.map((item, index) => (
        <article key={`${item.time}-${index}`}>
          <span aria-hidden="true" />
          <div>
            <time>{formatTime(item.time)}</time>
            <strong>{item.status || item.description || "Cập nhật hành trình"}</strong>
            {item.location ? <p>{item.location}</p> : null}
            {item.description && item.description !== item.status ? <small>{item.description}</small> : null}
          </div>
        </article>
      ))}
    </div>
  );
}

function CommandCard({
  icon,
  title,
  text,
  tone,
  onClick,
}: {
  icon: string;
  title: string;
  text: string;
  tone: "green" | "gold" | "ink" | "white";
  onClick: () => void;
}) {
  return (
    <button className={`factory-command ${tone}`} type="button" onClick={onClick}>
      <Icon icon={icon} size={21} />
      <span>{title}</span>
      <small>{text}</small>
    </button>
  );
}

function AuthRequired({ text, onLogin }: { text: string; onLogin: () => void }) {
  return (
    <div className="factory-route">
      <EmptyState title="Cần đăng nhập" text={text} icon="zi-user" />
      <ActionButton icon="zi-arrow-right" onClick={onLogin}>Đăng nhập</ActionButton>
    </div>
  );
}

export default function App() {
  const [screen, setScreen] = useState<ScreenKey>("dashboard");
  const [bootstrap, setBootstrap] = useState<BootstrapData | null>(null);
  const [countries, setCountries] = useState<CountryOption[]>([]);
  const [quoteForm, setQuoteForm] = useState<QuoteForm>(initialQuoteForm);
  const [requestForm, setRequestForm] = useState<ShippingRequestForm>(initialRequestForm);
  const [quote, setQuote] = useState<QuoteResult | null>(null);
  const [trackingCode, setTrackingCode] = useState("");
  const [tracking, setTracking] = useState<TrackingResult | null>(null);
  const [auth, setAuth] = useState<AuthPayload | null>(null);
  const [zaloProfile, setZaloProfile] = useState<ZaloUserInfo | null>(null);
  const [loginForm, setLoginForm] = useState({ username: "", password: "" });
  const [orders, setOrders] = useState<OrderSummary[]>([]);
  const [orderStatuses, setOrderStatuses] = useState<StatusOption[]>([]);
  const [orderSearch, setOrderSearch] = useState("");
  const [orderStatus, setOrderStatus] = useState("");
  const [selectedOrder, setSelectedOrder] = useState<OrderDetail | null>(null);
  const [orderBootstrap, setOrderBootstrap] = useState<OrderFormBootstrap | null>(null);
  const [orderForm, setOrderForm] = useState<OrderFormState>(initialOrderForm);
  const [priceBootstrap, setPriceBootstrap] = useState<PriceListBootstrap | null>(null);
  const [priceLists, setPriceLists] = useState<PriceListItem[]>([]);
  const [selectedPrice, setSelectedPrice] = useState<PriceListDetailResult | null>(null);
  const [priceSearch, setPriceSearch] = useState("");
  const [priceForm, setPriceForm] = useState<PriceFormState>(initialPriceForm);
  const [notice, setNotice] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");

  useEffect(() => {
    fetchBootstrap()
      .then((data) => {
        setBootstrap(data);
        setCountries(data.countries);
      })
      .catch(handleError);

    if (getAuthToken()) {
      restoreSession();
    }
  }, []);

  useEffect(() => {
    if (!quoteForm.service_id) {
      setCountries(bootstrap?.countries ?? []);
      return;
    }

    fetchCountries(quoteForm.service_id)
      .then((items) => {
        setCountries(items);
        if (!items.some((item) => String(item.id) === quoteForm.country_id)) {
          setQuoteForm((current) => ({ ...current, country_id: "" }));
        }
      })
      .catch(handleError);
  }, [bootstrap?.countries, quoteForm.country_id, quoteForm.service_id]);

  useEffect(() => {
    if (screen === "orders" && auth?.abilities.orders_view) {
      loadOrders();
    }
  }, [screen, auth?.abilities.orders_view]);

  useEffect(() => {
    if (screen === "order-create" && auth?.abilities.orders_create) {
      loadOrderBootstrap();
    }
  }, [screen, auth?.abilities.orders_create]);

  useEffect(() => {
    if (screen === "prices" && auth?.abilities.prices_manage) {
      loadPriceData();
    }
  }, [screen, auth?.abilities.prices_manage]);

  const selectedService = useMemo<ServiceOption | undefined>(
    () => bootstrap?.services.find((item) => String(item.id) === quoteForm.service_id),
    [bootstrap?.services, quoteForm.service_id],
  );

  const selectedCountry = useMemo<CountryOption | undefined>(
    () => countries.find((item) => String(item.id) === quoteForm.country_id),
    [countries, quoteForm.country_id],
  );

  const canViewOrders = Boolean(auth?.abilities.orders_view);
  const canCreateOrder = Boolean(auth?.abilities.orders_create);
  const canManagePrices = Boolean(auth?.abilities.prices_manage);
  const canDeletePrices = Boolean(auth?.abilities.prices_delete);
  const serviceOptions = optionsFrom(bootstrap?.services ?? []);
  const countryOptions = optionsFrom(countries);
  const orderServiceOptions = optionsFrom(orderBootstrap?.services ?? bootstrap?.services ?? []);
  const orderCountryOptions = optionsFrom(orderBootstrap?.countries ?? bootstrap?.countries ?? []);
  const priceServiceOptions = optionsFrom(priceBootstrap?.services ?? []);
  const priceCountryOptions = optionsFrom(priceBootstrap?.countries ?? []);

  function resetMessages() {
    setError("");
    setNotice("");
  }

  function handleError(err: unknown) {
    setError(err instanceof Error ? err.message : "Thao tác thất bại. Vui lòng thử lại.");
  }

  async function restoreSession() {
    try {
      setAuth(await fetchMe());
    } catch {
      clearAuthToken();
    }
  }

  async function submitQuote(event?: FormEvent) {
    event?.preventDefault();
    resetMessages();

    if (!quoteForm.service_id || !quoteForm.country_id || !quoteForm.g_weight) {
      setError("Vui lòng chọn dịch vụ, quốc gia nhận và nhập cân nặng.");
      return;
    }

    setBusy("quote");
    try {
      const result = await calculateQuote(quoteForm);
      setQuote(result);
      setNotice("Đã tính cước tham khảo.");
    } catch (err) {
      setQuote(null);
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitRequest(event?: FormEvent) {
    event?.preventDefault();
    resetMessages();

    if (!requestForm.requester_name || !requestForm.phone || !requestForm.pickup_address) {
      setError("Vui lòng nhập họ tên, số điện thoại và địa chỉ lấy hàng.");
      return;
    }

    if (!quoteForm.g_weight) {
      setError("Vui lòng nhập thông tin kiện hàng ở phần tra cước trước khi gửi yêu cầu.");
      setScreen("quote");
      return;
    }

    setBusy("pickup");
    try {
      const created = await createShippingRequest({
        requester_name: requestForm.requester_name,
        phone: requestForm.phone,
        email: requestForm.email || undefined,
        company: requestForm.company || undefined,
        pickup_address: requestForm.pickup_address,
        pickup_city: requestForm.pickup_city || undefined,
        receiver_country_id: selectedCountry?.id,
        receiver_country: selectedCountry?.name,
        service_id: selectedService?.id,
        service_name: selectedService?.name,
        package_count: Number(requestForm.package_count || 1),
        weight_kg: Number(quoteForm.g_weight),
        length_cm: numericValue(quoteForm.length),
        width_cm: numericValue(quoteForm.width),
        height_cm: numericValue(quoteForm.height),
        note: requestForm.note || undefined,
        quote_snapshot: quote ?? undefined,
      });
      setNotice(`Đã ghi nhận yêu cầu ${created.code}.`);
      setRequestForm(initialRequestForm);
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitTracking(event?: FormEvent, code = trackingCode) {
    event?.preventDefault();
    resetMessages();

    const trimmed = code.trim();
    if (!trimmed) {
      setError("Vui lòng nhập mã bill hoặc tracking code.");
      return;
    }

    setBusy("tracking");
    try {
      const result = await fetchTracking(trimmed);
      setTracking(result);
      setTrackingCode(trimmed);
      setScreen("tracking");
      setNotice("Đã tải hành trình đơn hàng.");
    } catch (err) {
      setTracking(null);
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitLogin(event?: FormEvent) {
    event?.preventDefault();
    resetMessages();

    if (!loginForm.username || !loginForm.password) {
      setError("Vui lòng nhập tên đăng nhập và mật khẩu.");
      return;
    }

    setBusy("login");
    try {
      const result = await login({
        username: loginForm.username,
        password: loginForm.password,
        device_name: "mobile-web",
        link_zalo: false,
      });
      setAuth(result);
      setNotice("Đăng nhập thành công.");
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitZaloLogin() {
    resetMessages();
    setBusy("zalo-login");

    try {
      const zalo = await getZaloLoginContext({ requestUserInfo: true });
      const result = await loginWithZalo(zalo.accessToken);
      setAuth(result);
      setZaloProfile(zalo.userInfo ?? null);
      setNotice(zalo.userInfo?.name ? `Đăng nhập Zalo thành công: ${zalo.userInfo.name}.` : "Đăng nhập Zalo thành công.");
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitLogout() {
    resetMessages();
    setBusy("logout");
    await logout();
    setAuth(null);
    setZaloProfile(null);
    setSelectedOrder(null);
    setNotice("Đã đăng xuất.");
    setBusy("");
    setScreen("dashboard");
  }

  async function loadOrders() {
    setBusy("orders");
    try {
      const result: OrderListResult = await fetchOrders({
        search: orderSearch,
        status: orderStatus,
        per_page: 20,
      });
      setOrders(result.items);
      setOrderStatuses(result.statuses);
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function openOrder(orderId: number) {
    resetMessages();
    setBusy(`order-${orderId}`);
    try {
      setSelectedOrder(await fetchOrder(orderId));
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function loadOrderBootstrap() {
    if (orderBootstrap) {
      return;
    }

    try {
      setOrderBootstrap(await fetchOrderFormBootstrap());
    } catch (err) {
      handleError(err);
    }
  }

  function buildOrderPayload(): CreateOrderPayload | null {
    if (!orderForm.service_id || !orderForm.country_id) {
      setError("Vui lòng chọn dịch vụ và quốc gia nhận.");
      return null;
    }

    if (!orderForm.sender_name || !orderForm.sender_phone || !orderForm.sender_address) {
      setError("Vui lòng nhập đủ thông tin người gửi.");
      return null;
    }

    if (!orderForm.receiver_name || !orderForm.receiver_phone || !orderForm.receiver_address) {
      setError("Vui lòng nhập đủ thông tin người nhận.");
      return null;
    }

    if (!orderForm.g_weight) {
      setError("Vui lòng nhập cân nặng kiện hàng.");
      return null;
    }

    const invoiceItems: OrderInvoiceItem[] = orderForm.invoice_name
      ? [
          {
            tenhang: orderForm.invoice_name,
            soluong: Number(orderForm.invoice_qty || 1),
            price: Number(orderForm.invoice_price || 0),
          },
        ]
      : [];

    return {
      service_id: Number(orderForm.service_id),
      country_id: Number(orderForm.country_id),
      sender: {
        name: orderForm.sender_name,
        phone: orderForm.sender_phone,
        email: orderForm.sender_email || undefined,
        company: orderForm.sender_company || undefined,
        address: orderForm.sender_address,
      },
      receiver: {
        name: orderForm.receiver_name,
        phone: orderForm.receiver_phone,
        email: orderForm.receiver_email || undefined,
        company: orderForm.receiver_company || undefined,
        address: orderForm.receiver_address,
        city: orderForm.receiver_city || undefined,
        state: orderForm.receiver_state || undefined,
        postcode: orderForm.receiver_postcode || undefined,
      },
      packages: [
        {
          number_of_package: Number(orderForm.package_count || 1),
          length: numericValue(orderForm.length),
          width: numericValue(orderForm.width),
          height: numericValue(orderForm.height),
          g_weight: Number(orderForm.g_weight),
        },
      ],
      invoice_items: invoiceItems,
      notes: orderForm.notes || undefined,
    };
  }

  async function submitCreateOrder(event?: FormEvent) {
    event?.preventDefault();
    resetMessages();

    const payload = buildOrderPayload();
    if (!payload) {
      return;
    }

    setBusy("create-order");
    try {
      const result = await createOrder(payload);
      setSelectedOrder(result.order);
      setOrderForm(initialOrderForm);
      setScreen("orders");
      setNotice(result.warnings.length ? `Đã tạo đơn, cần bổ sung: ${result.warnings.join(", ")}.` : "Đã tạo đơn hàng.");
      loadOrders();
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function loadPriceData() {
    setBusy("prices");
    try {
      const [bootstrapData, listData] = await Promise.all([
        priceBootstrap ? Promise.resolve(priceBootstrap) : fetchPriceBootstrap(),
        fetchPriceLists({ search: priceSearch, per_page: 20 }),
      ]);
      setPriceBootstrap(bootstrapData);
      setPriceLists(listData.items);
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function openPrice(id: number) {
    resetMessages();
    setBusy(`price-${id}`);
    try {
      const detail = await fetchPriceList(id);
      setSelectedPrice(detail);
      setPriceForm({
        id: detail.id,
        name: detail.name,
        service_id: String(detail.service.id),
        country_id: String(detail.countries[0]?.id ?? ""),
        details: detail.details.length
          ? detail.details.map((row) => ({
              quycach: row.quycach,
              weight_from: String(row.weight_from),
              weight_to: String(row.weight_to),
              sale_price: String(row.sale_price),
              cost_price: String(row.cost_price),
              base_price: String(row.base_price),
            }))
          : [{ ...emptyPriceDetail }],
      });
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  function resetPriceForm() {
    setSelectedPrice(null);
    setPriceForm(initialPriceForm);
  }

  function updatePriceRow(index: number, field: keyof PriceDetailForm, value: string) {
    setPriceForm((current) => ({
      ...current,
      details: current.details.map((row, rowIndex) => (rowIndex === index ? { ...row, [field]: value } : row)),
    }));
  }

  function addPriceRow() {
    setPriceForm((current) => ({
      ...current,
      details: [...current.details, { ...emptyPriceDetail }],
    }));
  }

  function removePriceRow(index: number) {
    setPriceForm((current) => ({
      ...current,
      details: current.details.filter((_, rowIndex) => rowIndex !== index),
    }));
  }

  function pricePayload(): { name: string; service_id: number; country_ids: number[]; details: PriceListDetail[] } | null {
    if (!priceForm.name || !priceForm.service_id || !priceForm.country_id) {
      setError("Vui lòng nhập tên bảng giá, dịch vụ và quốc gia.");
      return null;
    }

    return {
      name: priceForm.name,
      service_id: Number(priceForm.service_id),
      country_ids: [Number(priceForm.country_id)],
      details: priceForm.details.map((row) => ({
        quycach: row.quycach,
        weight_from: numberOrZero(row.weight_from),
        weight_to: numberOrZero(row.weight_to),
        sale_price: numberOrZero(row.sale_price),
        cost_price: numberOrZero(row.cost_price),
        base_price: numberOrZero(row.base_price),
      })),
    };
  }

  async function submitPriceForm(event?: FormEvent) {
    event?.preventDefault();
    resetMessages();

    const payload = pricePayload();
    if (!payload) {
      return;
    }

    setBusy("price-save");
    try {
      const saved = await savePriceList(payload, priceForm.id);
      setSelectedPrice(saved);
      setPriceForm({
        id: saved.id,
        name: saved.name,
        service_id: String(saved.service.id),
        country_id: String(saved.countries[0]?.id ?? ""),
        details: saved.details.length
          ? saved.details.map((row) => ({
              quycach: row.quycach,
              weight_from: String(row.weight_from),
              weight_to: String(row.weight_to),
              sale_price: String(row.sale_price),
              cost_price: String(row.cost_price),
              base_price: String(row.base_price),
            }))
          : [{ ...emptyPriceDetail }],
      });
      setNotice("Đã lưu bảng giá.");
      loadPriceData();
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  async function submitDeletePrice() {
    if (!priceForm.id || !window.confirm("Xóa bảng giá này?")) {
      return;
    }

    resetMessages();
    setBusy("price-delete");
    try {
      await deletePriceList(priceForm.id);
      resetPriceForm();
      setNotice("Đã xóa bảng giá.");
      loadPriceData();
    } catch (err) {
      handleError(err);
    } finally {
      setBusy("");
    }
  }

  const navItems = [
    { key: "dashboard" as const, label: "Home", icon: "zi-home" },
    { key: "quote" as const, label: "Cước", icon: "zi-search" },
    { key: "pickup" as const, label: "Pickup", icon: "zi-call" },
    { key: "tracking" as const, label: "Track", icon: "zi-location" },
    { key: "account" as const, label: auth ? "Account" : "Login", icon: "zi-user" },
  ];

  function renderDashboard() {
    const displayName = auth?.user.fullname || auth?.user.username || zaloProfile?.name || "Khách hàng";
    const defaultService = bootstrap?.services[0];
    const lanes = (bootstrap?.countries ?? []).slice(0, 4);
    const fallbackLanes = ["Hoa Kỳ", "Nhật Bản", "Hàn Quốc", "Úc"];

    function startQuote(country?: CountryOption) {
      setQuoteForm((current) => ({
        ...current,
        service_id: current.service_id || (defaultService ? String(defaultService.id) : ""),
        country_id: country ? String(country.id) : current.country_id,
      }));
      setScreen("quote");
    }

    return (
      <div className="factory-home">
        <section className="factory-hero">
          <div className="factory-hero-copy">
            <span>beeLogictic control room</span>
            <h1>{displayName}</h1>
            <p>Tra cước, đặt pickup, theo dõi vận đơn và thao tác nội bộ trên cùng một luồng mobile.</p>
          </div>

          <form className="factory-track-dock" onSubmit={(event) => submitTracking(event)}>
            <Icon icon="zi-search" size={20} />
            <Input
              clearable
              placeholder="Mã bill hoặc tracking"
              size="large"
              value={trackingCode}
              onChange={(event) => setTrackingCode(event.currentTarget.value)}
            />
            <ActionButton className="icon-only" htmlType="submit" loading={busy === "tracking"} icon="zi-arrow-right">
              <span className="sr-only">Tra cứu</span>
            </ActionButton>
          </form>

          <div className="factory-hero-board" aria-hidden="true">
            <div>
              <small>ETA</small>
              <strong>24-72h</strong>
            </div>
            <div>
              <small>DIM</small>
              <strong>{bootstrap?.dim ?? 6000}</strong>
            </div>
            <div>
              <small>Role</small>
              <strong>{auth ? auth.roles[0] ?? "user" : "guest"}</strong>
            </div>
          </div>
        </section>

        <section className="factory-command-grid" aria-label="Thao tác nhanh">
          <CommandCard icon="zi-search" title="Tính cước" text="Báo giá tuyến nhận" tone="green" onClick={() => startQuote()} />
          <CommandCard icon="zi-call" title="Đặt pickup" text="Yêu cầu lấy hàng" tone="gold" onClick={() => setScreen("pickup")} />
          <CommandCard icon="zi-location" title="Tracking" text="Hành trình vận đơn" tone="ink" onClick={() => setScreen("tracking")} />
          <CommandCard icon="zi-note" title="Đơn hàng" text={canViewOrders ? "Mở workspace" : "Cần đăng nhập"} tone="white" onClick={() => setScreen(canViewOrders ? "orders" : "account")} />
        </section>

        <Panel title="Tuyến nhanh" subtitle="Mở báo giá">
          <div className="factory-lane-grid">
            {lanes.length
              ? lanes.map((country) => (
                  <button key={country.id} type="button" onClick={() => startQuote(country)}>
                    <span>{country.name.slice(0, 2).toUpperCase()}</span>
                    <strong>{country.name}</strong>
                    <Icon icon="zi-chevron-right" size={15} />
                  </button>
                ))
              : fallbackLanes.map((country) => (
                  <button key={country} type="button" onClick={() => startQuote()}>
                    <span>{country.slice(0, 2).toUpperCase()}</span>
                    <strong>{country}</strong>
                    <Icon icon="zi-chevron-right" size={15} />
                  </button>
                ))}
          </div>
        </Panel>

        {(canViewOrders || canCreateOrder || canManagePrices) ? (
          <Panel title="Nội bộ" subtitle="Theo quyền tài khoản">
            <div className="factory-internal-grid">
              {canViewOrders ? <CommandCard icon="zi-note" title="Đơn hàng" text="Danh sách và chi tiết" tone="white" onClick={() => setScreen("orders")} /> : null}
              {canCreateOrder ? <CommandCard icon="zi-plus-circle" title="Tạo đơn" text="Lên vận đơn mới" tone="green" onClick={() => setScreen("order-create")} /> : null}
              {canManagePrices ? <CommandCard icon="zi-list-1" title="Bảng giá" text="Quản trị giá tuyến" tone="gold" onClick={() => setScreen("prices")} /> : null}
            </div>
          </Panel>
        ) : null}

        {quote ? (
          <Panel title="Quote gần nhất" subtitle={`${selectedService?.name ?? "Dịch vụ"} / ${selectedCountry?.name ?? "Tuyến nhận"}`}>
            <div className="factory-price-ticket">
              <span>Cước tham khảo</span>
              <strong>{money.format(quote.sale_price)}</strong>
              <p>Cân tính {quote.chargeable_weight} kg, đơn giá {money.format(quote.unit_price)}</p>
            </div>
          </Panel>
        ) : null}
      </div>
    );
  }

  function renderQuote() {
    return (
      <form className="factory-route" onSubmit={(event) => submitQuote(event)}>
        <PageFrame
          label="Báo giá"
          title="Tính cước quốc tế"
          text="Chọn tuyến, dịch vụ và thông tin kiện để nhận giá tham khảo từ dữ liệu Laravel."
          stats={[
            { label: "DIM", value: bootstrap?.dim ?? 6000 },
            { label: "Dịch vụ", value: bootstrap?.services.length ?? 0 },
            { label: "Quốc gia", value: countries.length },
          ]}
        >
          <Panel title="Thông tin kiện" subtitle="Bước tính cước">
            <div className="factory-form-grid">
              <SelectField label="Dịch vụ" placeholder="Chọn dịch vụ" value={quoteForm.service_id} options={serviceOptions} onChange={(value) => setQuoteForm((current) => ({ ...current, service_id: value }))} />
              <SelectField label="Quốc gia nhận" placeholder="Chọn quốc gia" value={quoteForm.country_id} options={countryOptions} onChange={(value) => setQuoteForm((current) => ({ ...current, country_id: value }))} />
              <Field label="Cân nặng" type="number" inputMode="decimal" suffix="kg" value={quoteForm.g_weight} onChange={(value) => setQuoteForm((current) => ({ ...current, g_weight: value }))} />
              <Field label="Số kiện" type="number" inputMode="numeric" value={requestForm.package_count} onChange={(value) => setRequestForm((current) => ({ ...current, package_count: value }))} />
              <Field label="Dài" type="number" inputMode="decimal" suffix="cm" value={quoteForm.length} onChange={(value) => setQuoteForm((current) => ({ ...current, length: value }))} />
              <Field label="Rộng" type="number" inputMode="decimal" suffix="cm" value={quoteForm.width} onChange={(value) => setQuoteForm((current) => ({ ...current, width: value }))} />
              <Field label="Cao" type="number" inputMode="decimal" suffix="cm" value={quoteForm.height} onChange={(value) => setQuoteForm((current) => ({ ...current, height: value }))} />
            </div>
            <ActionButton htmlType="submit" loading={busy === "quote"} icon="zi-arrow-right">Tính cước</ActionButton>
          </Panel>

          {quote ? (
            <section className="factory-result">
              <span>Cước tham khảo</span>
              <strong>{money.format(quote.sale_price)}</strong>
              <div className="factory-stat-grid">
                <StatBlock label="Cân tính" value={`${quote.chargeable_weight} kg`} />
                <StatBlock label="Đơn giá" value={money.format(quote.unit_price)} tone="green" />
              </div>
              <ActionButton tone="secondary" icon="zi-call" onClick={() => setScreen("pickup")}>Gửi yêu cầu lấy hàng</ActionButton>
            </section>
          ) : null}
        </PageFrame>
      </form>
    );
  }

  function renderPickup() {
    return (
      <form className="factory-route" onSubmit={(event) => submitRequest(event)}>
        <PageFrame
          label="Pickup"
          title="Đặt lịch lấy hàng"
          text="Gửi yêu cầu lấy hàng dựa trên báo giá và tuyến nhận đã chọn."
          stats={[
            { label: "Dịch vụ", value: selectedService?.name ?? "Chưa chọn" },
            { label: "Quốc gia", value: selectedCountry?.name ?? "Chưa chọn" },
            { label: "Số kiện", value: requestForm.package_count || 1 },
          ]}
        >
          <Panel title="Thông tin người gửi" subtitle="Laravel API sẽ tạo yêu cầu pickup">
            <div className="factory-form-grid">
              <Field label="Họ tên" value={requestForm.requester_name} onChange={(value) => setRequestForm((current) => ({ ...current, requester_name: value }))} />
              <Field label="Số điện thoại" type="tel" inputMode="tel" value={requestForm.phone} onChange={(value) => setRequestForm((current) => ({ ...current, phone: value }))} />
              <Field label="Email" type="email" value={requestForm.email} onChange={(value) => setRequestForm((current) => ({ ...current, email: value }))} />
              <Field label="Công ty" value={requestForm.company} onChange={(value) => setRequestForm((current) => ({ ...current, company: value }))} />
              <Field label="Địa chỉ lấy hàng" value={requestForm.pickup_address} onChange={(value) => setRequestForm((current) => ({ ...current, pickup_address: value }))} />
              <Field label="Tỉnh/thành" value={requestForm.pickup_city} onChange={(value) => setRequestForm((current) => ({ ...current, pickup_city: value }))} />
              <TextAreaField label="Ghi chú" value={requestForm.note} onChange={(value) => setRequestForm((current) => ({ ...current, note: value }))} />
            </div>
            <ActionButton htmlType="submit" loading={busy === "pickup"} icon="zi-arrow-right">Gửi yêu cầu</ActionButton>
          </Panel>
        </PageFrame>
      </form>
    );
  }

  function renderTracking() {
    return (
      <div className="factory-route">
        <PageFrame
          label="Tracking"
          title="Theo dõi vận đơn"
          text="Tra cứu trạng thái, người nhận, cân tính và timeline vận chuyển."
        >
          <Panel title="Nhập mã" subtitle="Bill hoặc tracking code">
            <form className="factory-search" onSubmit={(event) => submitTracking(event)}>
              <Input clearable placeholder="Nhập mã vận đơn" size="large" value={trackingCode} onChange={(event) => setTrackingCode(event.currentTarget.value)} />
              <ActionButton htmlType="submit" loading={busy === "tracking"} icon="zi-search">Tra</ActionButton>
            </form>
          </Panel>

          {tracking ? (
            <>
              <MetricStrip
                items={[
                  { label: "Bill", value: tracking.id_bill },
                  { label: "Điểm đến", value: tracking.receiver.destination },
                  { label: "Cân tính", value: `${tracking.chargeable_weight.value} ${tracking.chargeable_weight.unit}` },
                ]}
              />
              <Panel>
                <div className="factory-detail-head">
                  <div>
                    <span>{tracking.tracking_code ?? "Tracking"}</span>
                    <h2>{tracking.id_bill}</h2>
                  </div>
                  <StatusPill status={tracking.status} />
                </div>
                <div className="factory-stat-grid">
                  <StatBlock label="Người nhận" value={tracking.receiver.name} />
                  <StatBlock label="Điểm đến" value={tracking.receiver.destination} tone="green" />
                  <StatBlock label="Cân tính" value={`${tracking.chargeable_weight.value} ${tracking.chargeable_weight.unit}`} tone="gold" />
                </div>
                <Timeline items={tracking.shipping_history} />
              </Panel>
            </>
          ) : (
            <EmptyState title="Chưa có dữ liệu" text="Nhập mã vận đơn để xem hành trình." icon="zi-location" />
          )}
        </PageFrame>
      </div>
    );
  }

  function renderOrders() {
    if (!canViewOrders) {
      return <AuthRequired text="Đăng nhập để xem đơn hàng." onLogin={() => setScreen("account")} />;
    }

    const selectedStatusLabel = orderStatuses.find((status) => status.value === orderStatus)?.label ?? "Tất cả";

    return (
      <div className="factory-route">
        <PageFrame
          label="Workspace"
          title="Quản lý vận đơn"
          text="Bộ lọc mobile cho danh sách đơn, chi tiết vận chuyển và quyền tạo đơn."
          stats={[
            { label: "Hiển thị", value: orders.length },
            { label: "Trạng thái", value: selectedStatusLabel },
            { label: "Phạm vi", value: auth?.abilities.orders_scope ?? "none" },
          ]}
        >
          <Panel title="Bộ lọc" subtitle="Dữ liệu Laravel hiện có" action={canCreateOrder ? <ActionButton tone="secondary" icon="zi-plus" onClick={() => setScreen("order-create")}>Tạo</ActionButton> : null}>
            <div className="factory-stack">
              <div className="factory-search">
                <Input clearable placeholder="Mã bill, tracking, người nhận" size="large" value={orderSearch} onChange={(event) => setOrderSearch(event.currentTarget.value)} />
                <ActionButton tone="secondary" loading={busy === "orders"} icon="zi-search" onClick={loadOrders}>Lọc</ActionButton>
              </div>
              {orderStatuses.length ? (
                <div className="factory-filter-row">
                  <button className={orderStatus === "" ? "active" : ""} type="button" onClick={() => setOrderStatus("")}>Tất cả</button>
                  {orderStatuses.map((status) => (
                    <button className={orderStatus === status.value ? "active" : ""} key={status.value} type="button" onClick={() => setOrderStatus(status.value)}>
                      {status.label}
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
          </Panel>

          {busy === "orders" && !orders.length ? (
            <LoadingRows count={3} />
          ) : orders.length ? (
            <div className="factory-list">
              {orders.map((order) => (
                <button className="factory-order-row" key={order.id} type="button" onClick={() => openOrder(order.id)}>
                  <div>
                    <span>{order.tracking_code ?? "Chưa có tracking"}</span>
                    <strong>{order.id_bill}</strong>
                    <p>{order.receiver.name} / {order.receiver.destination}</p>
                  </div>
                  <StatusPill status={order.status} />
                </button>
              ))}
            </div>
          ) : (
            <EmptyState title="Chưa có đơn phù hợp" text="Thử đổi bộ lọc hoặc tạo đơn mới nếu tài khoản có quyền." icon="zi-note" />
          )}

          {selectedOrder ? (
            <Panel>
              <div className="factory-detail-head">
                <div>
                  <span>Chi tiết đơn hàng</span>
                  <h2>{selectedOrder.id_bill}</h2>
                </div>
                <StatusPill status={selectedOrder.status} />
              </div>
              <div className="factory-stat-grid">
                <StatBlock label="Người gửi" value={selectedOrder.sender.name} />
                <StatBlock label="Người nhận" value={selectedOrder.receiver.name} tone="green" />
                <StatBlock label="Cân tính" value={`${selectedOrder.chargeable_weight.value} ${selectedOrder.chargeable_weight.unit}`} tone="gold" />
              </div>
              <Timeline items={selectedOrder.shipping_history} />
            </Panel>
          ) : null}
        </PageFrame>
      </div>
    );
  }

  function renderOrderCreate() {
    if (!canCreateOrder) {
      return <AuthRequired text="Tài khoản hiện tại chưa có quyền tạo đơn hàng." onLogin={() => setScreen("account")} />;
    }

    return (
      <form className="factory-route" onSubmit={(event) => submitCreateOrder(event)}>
        <PageFrame
          label="Tạo vận đơn"
          title="Khởi tạo đơn mới"
          text="Form mobile nối thẳng API Laravel, giữ cấu trúc người gửi, người nhận và kiện hàng."
          stats={[
            { label: "Dịch vụ", value: orderServiceOptions.length },
            { label: "Quốc gia", value: orderCountryOptions.length },
            { label: "Số kiện", value: orderForm.package_count || 1 },
          ]}
        >
          <Panel title="Tuyến dịch vụ" subtitle="Bắt buộc">
            <div className="factory-form-grid">
              <SelectField label="Dịch vụ" placeholder="Chọn dịch vụ" value={orderForm.service_id} options={orderServiceOptions} onChange={(value) => setOrderForm((current) => ({ ...current, service_id: value }))} />
              <SelectField label="Quốc gia nhận" placeholder="Chọn quốc gia" value={orderForm.country_id} options={orderCountryOptions} onChange={(value) => setOrderForm((current) => ({ ...current, country_id: value }))} />
            </div>
          </Panel>

          <Panel title="Người gửi" subtitle="Thông tin khách gửi">
            <div className="factory-form-grid">
              <Field label="Tên người gửi" value={orderForm.sender_name} onChange={(value) => setOrderForm((current) => ({ ...current, sender_name: value }))} />
              <Field label="Điện thoại" type="tel" inputMode="tel" value={orderForm.sender_phone} onChange={(value) => setOrderForm((current) => ({ ...current, sender_phone: value }))} />
              <Field label="Email" type="email" value={orderForm.sender_email} onChange={(value) => setOrderForm((current) => ({ ...current, sender_email: value }))} />
              <Field label="Công ty" value={orderForm.sender_company} onChange={(value) => setOrderForm((current) => ({ ...current, sender_company: value }))} />
              <TextAreaField label="Địa chỉ" value={orderForm.sender_address} onChange={(value) => setOrderForm((current) => ({ ...current, sender_address: value }))} />
            </div>
          </Panel>

          <Panel title="Người nhận" subtitle="Điểm đến">
            <div className="factory-form-grid">
              <Field label="Tên người nhận" value={orderForm.receiver_name} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_name: value }))} />
              <Field label="Điện thoại" type="tel" inputMode="tel" value={orderForm.receiver_phone} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_phone: value }))} />
              <Field label="Email" type="email" value={orderForm.receiver_email} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_email: value }))} />
              <Field label="Công ty" value={orderForm.receiver_company} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_company: value }))} />
              <TextAreaField label="Địa chỉ" value={orderForm.receiver_address} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_address: value }))} />
              <Field label="City" value={orderForm.receiver_city} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_city: value }))} />
              <Field label="State" value={orderForm.receiver_state} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_state: value }))} />
              <Field label="Postcode" value={orderForm.receiver_postcode} onChange={(value) => setOrderForm((current) => ({ ...current, receiver_postcode: value }))} />
            </div>
          </Panel>

          <Panel title="Kiện hàng" subtitle="Thông tin khai báo">
            <div className="factory-form-grid">
              <Field label="Số kiện" type="number" inputMode="numeric" value={orderForm.package_count} onChange={(value) => setOrderForm((current) => ({ ...current, package_count: value }))} />
              <Field label="Cân nặng" type="number" inputMode="decimal" suffix="kg" value={orderForm.g_weight} onChange={(value) => setOrderForm((current) => ({ ...current, g_weight: value }))} />
              <Field label="Dài" type="number" inputMode="decimal" suffix="cm" value={orderForm.length} onChange={(value) => setOrderForm((current) => ({ ...current, length: value }))} />
              <Field label="Rộng" type="number" inputMode="decimal" suffix="cm" value={orderForm.width} onChange={(value) => setOrderForm((current) => ({ ...current, width: value }))} />
              <Field label="Cao" type="number" inputMode="decimal" suffix="cm" value={orderForm.height} onChange={(value) => setOrderForm((current) => ({ ...current, height: value }))} />
              <Field label="Tên hàng" value={orderForm.invoice_name} onChange={(value) => setOrderForm((current) => ({ ...current, invoice_name: value }))} />
              <Field label="Số lượng" type="number" inputMode="numeric" value={orderForm.invoice_qty} onChange={(value) => setOrderForm((current) => ({ ...current, invoice_qty: value }))} />
              <Field label="Giá trị" type="number" inputMode="decimal" value={orderForm.invoice_price} onChange={(value) => setOrderForm((current) => ({ ...current, invoice_price: value }))} />
              <TextAreaField label="Ghi chú" value={orderForm.notes} onChange={(value) => setOrderForm((current) => ({ ...current, notes: value }))} />
            </div>
            <ActionButton htmlType="submit" loading={busy === "create-order"} icon="zi-arrow-right">Tạo đơn hàng</ActionButton>
          </Panel>
        </PageFrame>
      </form>
    );
  }

  function renderPrices() {
    if (!canManagePrices) {
      return <AuthRequired text="Tài khoản hiện tại chưa có quyền quản lý bảng giá." onLogin={() => setScreen("account")} />;
    }

    return (
      <div className="factory-route">
        <PageFrame
          label="Bảng giá"
          title="Factory giá tuyến"
          text="Tạo và cập nhật bảng giá theo dịch vụ, quốc gia và ngưỡng cân."
          stats={[
            { label: "Bảng giá", value: priceLists.length },
            { label: "Dịch vụ", value: priceServiceOptions.length },
            { label: "Quốc gia", value: priceCountryOptions.length },
          ]}
        >
          <Panel title="Tìm bảng giá" subtitle="Dữ liệu nội bộ">
            <div className="factory-search">
              <Input clearable placeholder="Tên bảng giá, dịch vụ, quốc gia" size="large" value={priceSearch} onChange={(event) => setPriceSearch(event.currentTarget.value)} />
              <ActionButton tone="secondary" loading={busy === "prices"} icon="zi-search" onClick={loadPriceData}>Lọc</ActionButton>
            </div>
          </Panel>

          {busy === "prices" && !priceLists.length ? (
            <LoadingRows count={3} />
          ) : priceLists.length ? (
            <div className="factory-list">
              {priceLists.map((item) => (
                <button className={selectedPrice?.id === item.id ? "factory-price-row active" : "factory-price-row"} key={item.id} type="button" onClick={() => openPrice(item.id)}>
                  <span>{item.service.name ?? "Dịch vụ"}</span>
                  <strong>{item.name}</strong>
                  <p>{item.countries.map((country) => country.name).join(", ")} / {item.details_count} dòng giá</p>
                </button>
              ))}
            </div>
          ) : (
            <EmptyState title="Chưa có bảng giá" text="Tạo bảng giá đầu tiên hoặc thử thay đổi từ khóa tìm kiếm." icon="zi-list-1" />
          )}

          <form className="factory-route compact" onSubmit={(event) => submitPriceForm(event)}>
            <Panel title={priceForm.id ? "Cập nhật bảng giá" : "Tạo bảng giá"} subtitle={selectedPrice?.name ?? "Biểu giá mới"}>
              <div className="factory-form-grid">
                <Field label="Tên bảng giá" value={priceForm.name} onChange={(value) => setPriceForm((current) => ({ ...current, name: value }))} />
                <SelectField label="Dịch vụ" placeholder="Chọn dịch vụ" value={priceForm.service_id} options={priceServiceOptions} onChange={(value) => setPriceForm((current) => ({ ...current, service_id: value }))} />
                <SelectField label="Quốc gia" placeholder="Chọn quốc gia" value={priceForm.country_id} options={priceCountryOptions} onChange={(value) => setPriceForm((current) => ({ ...current, country_id: value }))} />
              </div>
            </Panel>

            {priceForm.details.map((row, index) => (
              <Panel key={`${row.quycach}-${index}`} title={`Dòng giá ${index + 1}`} subtitle="Quy tắc cân nặng">
                <div className="factory-form-grid">
                  <SelectField
                    label="Quy cách"
                    placeholder="Chọn quy cách"
                    value={row.quycach}
                    options={[
                      { value: "DON_GIA", label: "Đơn giá" },
                      { value: "CO_DINH", label: "Cố định" },
                    ]}
                    onChange={(value) => updatePriceRow(index, "quycach", value)}
                  />
                  <Field label="Từ kg" type="number" inputMode="decimal" value={row.weight_from} onChange={(value) => updatePriceRow(index, "weight_from", value)} />
                  <Field label="Đến kg" type="number" inputMode="decimal" value={row.weight_to} onChange={(value) => updatePriceRow(index, "weight_to", value)} />
                  <Field label="Giá bán" type="number" inputMode="decimal" value={row.sale_price} onChange={(value) => updatePriceRow(index, "sale_price", value)} />
                  <Field label="Giá vốn" type="number" inputMode="decimal" value={row.cost_price} onChange={(value) => updatePriceRow(index, "cost_price", value)} />
                  <Field label="Giá nền" type="number" inputMode="decimal" value={row.base_price} onChange={(value) => updatePriceRow(index, "base_price", value)} />
                </div>
                {priceForm.details.length > 1 ? <ActionButton tone="quiet" onClick={() => removePriceRow(index)}>Xóa dòng</ActionButton> : null}
              </Panel>
            ))}

            <div className="factory-action-bar">
              <ActionButton tone="secondary" icon="zi-plus" onClick={addPriceRow}>Thêm dòng</ActionButton>
              <ActionButton htmlType="submit" loading={busy === "price-save"} icon="zi-arrow-right">Lưu</ActionButton>
              <ActionButton tone="quiet" onClick={resetPriceForm}>Tạo mới</ActionButton>
              {canDeletePrices && priceForm.id ? <ActionButton tone="danger" loading={busy === "price-delete"} onClick={submitDeletePrice}>Xóa</ActionButton> : null}
            </div>
          </form>
        </PageFrame>
      </div>
    );
  }

  function renderAccount() {
    if (!auth) {
      return (
        <form className="factory-route" onSubmit={(event) => submitLogin(event)}>
          <PageFrame
            label="Tài khoản"
            title="Đăng nhập hệ thống"
            text="Dùng tài khoản Laravel hoặc Zalo đã liên kết để mở đúng quyền khách hàng và nội bộ."
          >
            <Panel title="Thông tin đăng nhập" subtitle="beeLogictic">
              <div className="factory-form-grid single">
                <Field label="Tên đăng nhập" value={loginForm.username} onChange={(value) => setLoginForm((current) => ({ ...current, username: value }))} />
                <Field label="Mật khẩu" type="password" value={loginForm.password} onChange={(value) => setLoginForm((current) => ({ ...current, password: value }))} />
                <ActionButton htmlType="submit" loading={busy === "login"} icon="zi-arrow-right">Đăng nhập</ActionButton>
                <div className="factory-split"><span>hoặc</span></div>
                <ActionButton tone="secondary" loading={busy === "zalo-login"} icon="zi-user" onClick={submitZaloLogin}>Đăng nhập bằng Zalo</ActionButton>
              </div>
            </Panel>
          </PageFrame>
        </form>
      );
    }

    return (
      <div className="factory-route">
        <PageFrame
          label="Tài khoản"
          title={auth.user.fullname || auth.user.username}
          text="Quản lý phiên đăng nhập, liên kết Zalo và quyền truy cập trong Mini App."
          stats={[
            { label: "Vai trò", value: auth.roles.length },
            { label: "Zalo", value: auth.user.zalo_linked || zaloProfile ? "Đã liên kết" : "Chưa liên kết" },
            { label: "Phạm vi", value: auth.abilities.orders_scope },
          ]}
        >
          <section className="factory-profile">
            <div>{initials(auth.user.fullname || auth.user.username)}</div>
            <article>
              <span>Tài khoản</span>
              <h2>{auth.user.fullname || auth.user.username}</h2>
              <p>{auth.roles.join(", ")}</p>
            </article>
          </section>

          {auth.user.zalo_linked || zaloProfile ? (
            <Panel title="Zalo" subtitle={zaloProfile?.name ? `Đã xác thực: ${zaloProfile.name}` : "Đã liên kết Zalo"}>
              <div className="factory-stat-grid">
                <StatBlock label="Zalo ID" value={zaloProfile?.id ?? (auth.user.zalo_linked ? "Đã xác thực" : "Chưa có")} tone="green" />
                <StatBlock label="OA" value={zaloProfile?.followedOA ? "Đã quan tâm" : "Chưa xác định"} />
              </div>
            </Panel>
          ) : null}

          <Panel title="Quyền truy cập" subtitle="Theo Laravel abilities">
            <div className="factory-stat-grid">
              <StatBlock label="Xem đơn" value={canViewOrders ? "Có" : "Không"} tone={canViewOrders ? "green" : "base"} />
              <StatBlock label="Tạo đơn" value={canCreateOrder ? "Có" : "Không"} tone={canCreateOrder ? "green" : "base"} />
              <StatBlock label="Bảng giá" value={canManagePrices ? "Có" : "Không"} tone={canManagePrices ? "green" : "base"} />
            </div>
            <ActionButton tone="secondary" loading={busy === "logout"} onClick={submitLogout}>Đăng xuất</ActionButton>
          </Panel>
        </PageFrame>
      </div>
    );
  }

  function renderScreen() {
    switch (screen) {
      case "quote":
        return renderQuote();
      case "pickup":
        return renderPickup();
      case "tracking":
        return renderTracking();
      case "orders":
        return renderOrders();
      case "order-create":
        return renderOrderCreate();
      case "prices":
        return renderPrices();
      case "account":
        return renderAccount();
      default:
        return renderDashboard();
    }
  }

  return (
    <div className="factory-shell">
      <header className="factory-top">
        <button className="factory-brand" type="button" onClick={() => setScreen("dashboard")}>
          <span>BL</span>
          <strong>beeLogictic</strong>
          <small>Mini App</small>
        </button>
        <button className="factory-user" type="button" onClick={() => setScreen("account")}>
          {auth ? initials(auth.user.fullname || auth.user.username) : "ĐN"}
        </button>
      </header>

      <main className="factory-main">
        {error ? <Notice kind="error">{error}</Notice> : null}
        {notice ? <Notice kind="success">{notice}</Notice> : null}
        {renderScreen()}
      </main>

      <nav className="factory-tabbar" aria-label="Điều hướng chính">
        {navItems.map((item) => (
          <button className={screen === item.key ? "active" : ""} key={item.key} type="button" onClick={() => setScreen(item.key)}>
            <Icon icon={item.icon} size={19} />
            <span>{item.label}</span>
          </button>
        ))}
      </nav>
    </div>
  );
}

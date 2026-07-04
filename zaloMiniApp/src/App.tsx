import { useEffect, useMemo, useState } from "react";
import {
  App as ZMPApp,
  Box,
  Button,
  Header,
  Icon,
  Input,
  Page,
  Select,
  Tabs,
  Text,
} from "zmp-ui";
import {
  calculateQuote,
  createShippingRequest,
  fetchBootstrap,
  fetchCountries,
} from "./services/api";
import type {
  BootstrapData,
  CountryOption,
  QuoteForm,
  QuoteResult,
  ServiceOption,
  ShippingRequestForm,
} from "./types";

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

const money = new Intl.NumberFormat("vi-VN", {
  style: "currency",
  currency: "VND",
  maximumFractionDigits: 0,
});

function numericValue(value: string): number | undefined {
  return value ? Number(value) : undefined;
}

type NoticeProps = {
  kind: "error" | "success";
  children: string;
};

function Notice({ kind, children }: NoticeProps) {
  return (
    <Box className={`notice ${kind}`} p={3} flex alignItems="flex-start">
      <Icon
        icon={kind === "error" ? "zi-warning-circle" : "zi-check-circle"}
        size={20}
        className="notice-icon"
      />
      <Text size="small" className="notice-text">
        {children}
      </Text>
    </Box>
  );
}

type MetricProps = {
  label: string;
  value: string;
  muted?: boolean;
};

function Metric({ label, value, muted = false }: MetricProps) {
  return (
    <Box className={muted ? "metric muted" : "metric"} p={3}>
      <Text size="xxSmall" className="metric-label">
        {label}
      </Text>
      <Text.Title size="small" className="metric-value">
        {value}
      </Text.Title>
    </Box>
  );
}

export default function App() {
  const [activeTab, setActiveTab] = useState<"quote" | "request">("quote");
  const [bootstrap, setBootstrap] = useState<BootstrapData | null>(null);
  const [countries, setCountries] = useState<CountryOption[]>([]);
  const [quoteForm, setQuoteForm] = useState<QuoteForm>(initialQuoteForm);
  const [requestForm, setRequestForm] = useState<ShippingRequestForm>(initialRequestForm);
  const [quote, setQuote] = useState<QuoteResult | null>(null);
  const [notice, setNotice] = useState<string>("");
  const [error, setError] = useState<string>("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchBootstrap()
      .then((data) => {
        setBootstrap(data);
        setCountries(data.countries);
      })
      .catch((err: Error) => setError(err.message));
  }, []);

  useEffect(() => {
    if (!quoteForm.service_id) {
      return;
    }

    fetchCountries(quoteForm.service_id)
      .then((items) => {
        setCountries(items);
        if (!items.some((item) => String(item.id) === quoteForm.country_id)) {
          setQuoteForm((current) => ({ ...current, country_id: "" }));
        }
      })
      .catch((err: Error) => setError(err.message));
  }, [quoteForm.country_id, quoteForm.service_id]);

  const selectedService = useMemo<ServiceOption | undefined>(
    () => bootstrap?.services.find((item) => String(item.id) === quoteForm.service_id),
    [bootstrap?.services, quoteForm.service_id],
  );
  const selectedCountry = useMemo<CountryOption | undefined>(
    () => countries.find((item) => String(item.id) === quoteForm.country_id),
    [countries, quoteForm.country_id],
  );

  async function submitQuote() {
    setError("");
    setNotice("");

    if (!quoteForm.service_id || !quoteForm.country_id || !quoteForm.g_weight) {
      setError("Vui lòng chọn dịch vụ, quốc gia nhận và nhập cân nặng.");
      return;
    }

    setLoading(true);
    try {
      const result = await calculateQuote(quoteForm);
      setQuote(result);
      setNotice("Đã tính cước tham khảo. Bạn có thể gửi yêu cầu để nhân viên liên hệ.");
    } catch (err) {
      setQuote(null);
      setError(err instanceof Error ? err.message : "Không thể tính cước.");
    } finally {
      setLoading(false);
    }
  }

  async function submitRequest() {
    setError("");
    setNotice("");

    if (!requestForm.requester_name || !requestForm.phone || !requestForm.pickup_address) {
      setError("Vui lòng nhập họ tên, số điện thoại và địa chỉ lấy hàng.");
      return;
    }

    if (!quoteForm.g_weight) {
      setError("Vui lòng nhập thông tin kiện hàng ở phần tra cước trước khi gửi yêu cầu.");
      setActiveTab("quote");
      return;
    }

    setLoading(true);
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
      setNotice(`Đã ghi nhận yêu cầu ${created.code}. Nhân viên sẽ liên hệ xác nhận.`);
      setRequestForm(initialRequestForm);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Không thể gửi yêu cầu.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <ZMPApp theme="light">
      <Page name="shipping-request" className="app-page" hideScrollbar>
        <Header
          title="Hệ Thống Logistics"
          showBackIcon={false}
          backgroundColor="#ffffff"
          textColor="#111827"
        />

        <Box className="content" px={4} pb={4}>
          <Box className="hero" p={4}>
            <Text size="xSmall" bold className="hero-eyebrow">
              Zalo Mini App
            </Text>
            <Text.Title size="large" className="hero-title">
              Tra cước và gửi yêu cầu lấy hàng
            </Text.Title>
            <Box className="hero-metrics" mt={4}>
              <Metric label="Tuyến nhận" value={selectedCountry?.name ?? "Chưa chọn"} muted />
              <Metric label="Dịch vụ" value={selectedService?.name ?? "Chưa chọn"} muted />
            </Box>
          </Box>

          {error ? <Notice kind="error">{error}</Notice> : null}
          {notice ? <Notice kind="success">{notice}</Notice> : null}

          <Tabs
            activeKey={activeTab}
            onChange={(key) => setActiveTab(key as "quote" | "request")}
            destroyInactiveTabPane={false}
            className="task-tabs"
          >
            <Tabs.Tab key="quote" label="Tra cước">
              <Box className="form-panel" p={4}>
                <Box className="section-heading" flex justifyContent="space-between" mb={4}>
                  <Box>
                    <Text.Header>Thông tin kiện hàng</Text.Header>
                    <Text size="xSmall" className="section-subtitle">
                      Cước tham khảo, chưa gồm phụ phí và VAT.
                    </Text>
                  </Box>
                  <Box className="dim-chip" px={3} py={1}>
                    DIM {bootstrap?.dim ?? 6000}
                  </Box>
                </Box>

                <Box className="field-stack">
                  <Select
                    label="Dịch vụ"
                    placeholder="Chọn dịch vụ"
                    value={quoteForm.service_id || undefined}
                    closeOnSelect
                    onChange={(selected) =>
                      setQuoteForm((current) => ({
                        ...current,
                        service_id: String(selected ?? ""),
                      }))
                    }
                  >
                    {bootstrap?.services.map((service) => (
                      <Select.Option key={service.id} value={String(service.id)} title={service.name} />
                    ))}
                  </Select>

                  <Select
                    label="Quốc gia nhận"
                    placeholder="Chọn quốc gia"
                    value={quoteForm.country_id || undefined}
                    closeOnSelect
                    helperText={
                      quoteForm.service_id
                        ? "Danh sách đã lọc theo bảng giá của dịch vụ."
                        : "Chọn dịch vụ trước để lọc quốc gia."
                    }
                    onChange={(selected) =>
                      setQuoteForm((current) => ({
                        ...current,
                        country_id: String(selected ?? ""),
                      }))
                    }
                  >
                    {countries.map((country) => (
                      <Select.Option key={country.id} value={String(country.id)} title={country.name} />
                    ))}
                  </Select>

                  <Box className="field-grid two">
                    <Input
                      label="Cân nặng thực tế"
                      type="number"
                      size="large"
                      suffix="kg"
                      value={quoteForm.g_weight}
                      onChange={(event) =>
                        setQuoteForm((current) => ({
                          ...current,
                          g_weight: event.target.value,
                        }))
                      }
                    />
                    <Input
                      label="Số kiện"
                      type="number"
                      size="large"
                      value={requestForm.package_count}
                      onChange={(event) =>
                        setRequestForm((current) => ({
                          ...current,
                          package_count: event.target.value,
                        }))
                      }
                    />
                  </Box>

                  <Box className="field-grid three">
                    <Input
                      label="Dài"
                      type="number"
                      suffix="cm"
                      value={quoteForm.length}
                      onChange={(event) =>
                        setQuoteForm((current) => ({
                          ...current,
                          length: event.target.value,
                        }))
                      }
                    />
                    <Input
                      label="Rộng"
                      type="number"
                      suffix="cm"
                      value={quoteForm.width}
                      onChange={(event) =>
                        setQuoteForm((current) => ({
                          ...current,
                          width: event.target.value,
                        }))
                      }
                    />
                    <Input
                      label="Cao"
                      type="number"
                      suffix="cm"
                      value={quoteForm.height}
                      onChange={(event) =>
                        setQuoteForm((current) => ({
                          ...current,
                          height: event.target.value,
                        }))
                      }
                    />
                  </Box>

                  <Button
                    fullWidth
                    size="large"
                    loading={loading}
                    prefixIcon={<Icon icon="zi-search" />}
                    onClick={submitQuote}
                  >
                    Tính cước
                  </Button>
                </Box>

                {quote ? (
                  <Box className="quote-summary" mt={4}>
                    <Metric label="Cước tham khảo" value={money.format(quote.sale_price)} />
                    <Metric label="Cân tính cước" value={`${quote.chargeable_weight} kg`} />
                    <Metric label="Đơn giá" value={money.format(quote.unit_price)} />
                  </Box>
                ) : null}
              </Box>
            </Tabs.Tab>

            <Tabs.Tab key="request" label="Gửi yêu cầu">
              <Box className="form-panel" p={4}>
                <Box className="section-heading" mb={4}>
                  <Text.Header>Thông tin lấy hàng</Text.Header>
                  <Text size="xSmall" className="section-subtitle">
                    Nhân viên sẽ gọi xác nhận trước khi tạo đơn chính thức.
                  </Text>
                </Box>

                <Box className="field-stack">
                  <Input
                    label="Họ tên"
                    size="large"
                    clearable
                    value={requestForm.requester_name}
                    onChange={(event) =>
                      setRequestForm((current) => ({
                        ...current,
                        requester_name: event.target.value,
                      }))
                    }
                  />

                  <Box className="field-grid two">
                    <Input
                      label="Điện thoại"
                      inputMode="tel"
                      size="large"
                      value={requestForm.phone}
                      onChange={(event) =>
                        setRequestForm((current) => ({
                          ...current,
                          phone: event.target.value,
                        }))
                      }
                    />
                    <Input
                      label="Email"
                      size="large"
                      value={requestForm.email}
                      onChange={(event) =>
                        setRequestForm((current) => ({
                          ...current,
                          email: event.target.value,
                        }))
                      }
                    />
                  </Box>

                  <Input
                    label="Công ty"
                    value={requestForm.company}
                    onChange={(event) =>
                      setRequestForm((current) => ({
                        ...current,
                        company: event.target.value,
                      }))
                    }
                  />

                  <Input.TextArea
                    label="Địa chỉ lấy hàng"
                    autoHeight
                    showCount
                    maxLength={500}
                    value={requestForm.pickup_address}
                    onChange={(event) =>
                      setRequestForm((current) => ({
                        ...current,
                        pickup_address: event.target.value,
                      }))
                    }
                  />

                  <Input
                    label="Tỉnh/thành lấy hàng"
                    value={requestForm.pickup_city}
                    onChange={(event) =>
                      setRequestForm((current) => ({
                        ...current,
                        pickup_city: event.target.value,
                      }))
                    }
                  />

                  <Input.TextArea
                    label="Ghi chú"
                    autoHeight
                    showCount
                    maxLength={1000}
                    value={requestForm.note}
                    onChange={(event) =>
                      setRequestForm((current) => ({
                        ...current,
                        note: event.target.value,
                      }))
                    }
                  />

                  <Box className="request-summary" p={3}>
                    <Box>
                      <Text size="xSmall" className="metric-label">
                        {selectedService?.name ?? "Chưa chọn dịch vụ"}
                      </Text>
                      <Text.Title size="small">
                        {selectedCountry?.name ?? "Chưa chọn quốc gia nhận"}
                      </Text.Title>
                    </Box>
                    <Text.Title size="small" className="price-text">
                      {quote ? money.format(quote.sale_price) : "Chưa tính cước"}
                    </Text.Title>
                  </Box>

                  <Button
                    fullWidth
                    size="large"
                    variant="secondary"
                    loading={loading}
                    prefixIcon={<Icon icon="zi-send-solid" />}
                    onClick={submitRequest}
                  >
                    Gửi yêu cầu lấy hàng
                  </Button>
                </Box>
              </Box>
            </Tabs.Tab>
          </Tabs>
        </Box>
      </Page>
    </ZMPApp>
  );
}

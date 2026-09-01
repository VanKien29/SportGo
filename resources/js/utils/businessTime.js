export const BUSINESS_TIMEZONE = import.meta.env.VITE_BUSINESS_TIMEZONE || "Asia/Ho_Chi_Minh";

function partsFor(value = new Date()) {
  const date = value instanceof Date ? value : new Date(value);
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: BUSINESS_TIMEZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  }).formatToParts(date);

  return Object.fromEntries(
    parts
      .filter(({ type }) => type !== "literal")
      .map(({ type, value: partValue }) => [type, partValue]),
  );
}

export function businessDateString(value = new Date()) {
  const parts = partsFor(value);
  return `${parts.year}-${parts.month}-${parts.day}`;
}

export function businessDateLabel(value) {
  if (!value) return "";
  const raw = String(value);
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const [year, month, day] = raw.split("-");
    return `${day}/${month}/${year}`;
  }

  const parsed = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(parsed.getTime())) return "";

  const date = businessDateString(parsed);
  const [year, month, day] = date.split("-");
  return `${day}/${month}/${year}`;
}

export function businessMinutes(value = new Date()) {
  const parts = partsFor(value);
  return Number(parts.hour) * 60 + Number(parts.minute);
}

export function businessTimeString(value = new Date(), includeSeconds = false) {
  const parts = partsFor(value);
  return includeSeconds
    ? `${parts.hour}:${parts.minute}:${parts.second}`
    : `${parts.hour}:${parts.minute}`;
}

export function businessWeekDayIndex(value = new Date()) {
  const weekday = new Intl.DateTimeFormat("en-US", {
    timeZone: BUSINESS_TIMEZONE,
    weekday: "short",
  }).format(value instanceof Date ? value : new Date(value));
  return ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(weekday);
}

export function addCalendarDays(dateString, amount) {
  const [year, month, day] = String(dateString).split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  date.setUTCDate(date.getUTCDate() + Number(amount));

  return [date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate()]
    .map((part) => String(part).padStart(2, "0"))
    .join("-");
}

export function addCalendarMonths(dateString, amount) {
  const [year, month, day] = String(dateString).split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, 1));
  date.setUTCMonth(date.getUTCMonth() + Number(amount));
  const lastDay = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
  date.setUTCDate(Math.min(day, lastDay));

  return [date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate()]
    .map((part) => String(part).padStart(2, "0"))
    .join("-");
}

export function businessDateTime(dateString, timeString) {
  const date = String(dateString || "");
  const time = String(timeString || "").slice(0, 8);

  if (!date || !time) return new Date(NaN);

  if (time === "24:00:00" || time === "24:00") {
    return new Date(`${addCalendarDays(date, 1)}T00:00:00+07:00`);
  }

  const normalizedTime = time.length === 5 ? `${time}:00` : time;
  return new Date(`${date}T${normalizedTime}+07:00`);
}

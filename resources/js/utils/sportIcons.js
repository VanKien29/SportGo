const SPORT_ICON_RULES = [
  { icon: "badminton", keywords: ["cầu lông", "badminton"] },
  { icon: "pickleball", keywords: ["pickleball"] },
  { icon: "football", keywords: ["bóng đá", "football", "futsal"] },
  { icon: "basketball", keywords: ["bóng rổ", "basketball"] },
  { icon: "tennis", keywords: ["tennis", "quần vợt"] },
  { icon: "volleyball", keywords: ["bóng chuyền", "volleyball"] },
];

export const SPORT_ICON_OPTIONS = [
  { key: "badminton", label: "Cầu lông" },
  { key: "pickleball", label: "Pickleball" },
  { key: "football", label: "Bóng đá" },
  { key: "basketball", label: "Bóng rổ" },
  { key: "tennis", label: "Tennis / Quần vợt" },
  { key: "volleyball", label: "Bóng chuyền" },
];

export function sportIconKeyFromName(value, fallback = "activity") {
  const name = String(value || "").trim().toLocaleLowerCase("vi-VN");
  if (!name) return fallback;

  return SPORT_ICON_RULES.find(({ keywords }) =>
    keywords.some((keyword) => name.includes(keyword))
  )?.icon || fallback;
}

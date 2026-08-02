import { parseClientEnvironment } from "@/lib/env/schema";

export const clientEnvironment = parseClientEnvironment({
  NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
});

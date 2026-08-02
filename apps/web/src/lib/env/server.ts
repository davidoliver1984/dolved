import "server-only";

import { parseServerEnvironment } from "@/lib/env/schema";

export const serverEnvironment = parseServerEnvironment({
  API_INTERNAL_URL: process.env.API_INTERNAL_URL,
  FRONTEND_URL: process.env.FRONTEND_URL,
});

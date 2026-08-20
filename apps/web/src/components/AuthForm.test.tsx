import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api";

const { apiFetchMock, routerPushMock } = vi.hoisted(() => ({
  apiFetchMock: vi.fn(),
  routerPushMock: vi.fn(),
}));

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  apiFetch: apiFetchMock,
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: routerPushMock }),
}));

import { AuthForm } from "@/components/AuthForm";

describe("AuthForm", () => {
  beforeEach(() => {
    apiFetchMock.mockReset();
    routerPushMock.mockReset();
  });

  afterEach(cleanup);

  it.each(["login", "register"] as const)(
    "continues an already-authenticated %s session into the application",
    async (mode) => {
      apiFetchMock.mockRejectedValue(
        new ApiError("You are already signed in.", 409),
      );
      render(<AuthForm mode={mode} />);

      if (mode === "register") {
        fireEvent.change(screen.getByLabelText("Name"), {
          target: { value: "David Oliver" },
        });
      }
      fireEvent.change(screen.getByLabelText("Email address"), {
        target: { value: "david@example.test" },
      });
      fireEvent.change(screen.getByLabelText("Password"), {
        target: { value: "Correct-Horse-7!" },
      });
      if (mode === "register") {
        fireEvent.change(screen.getByLabelText("Confirm password"), {
          target: { value: "Correct-Horse-7!" },
        });
      }

      fireEvent.submit(screen.getByRole("button", {
        name: mode === "login" ? "Sign in" : "Create account",
      }).closest("form")!);

      await waitFor(() => expect(routerPushMock).toHaveBeenCalledWith("/app"));
      expect(screen.queryByRole("alert")).toBeNull();
    },
  );
});

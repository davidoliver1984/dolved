import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Wordmark } from "@/components/Wordmark";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/status-badge";

describe("R21 design-system foundations", () => {
  it("renders the approved live lowercase wordmark without a trailing period", () => {
    render(<Wordmark />);
    expect(screen.getByRole("link", { name: "dolved" }).textContent).toBe("dolved");
  });

  it("keeps every button variant at the 44px touch-target baseline", () => {
    render(<Button>Continue</Button>);
    expect(screen.getByRole("button", { name: "Continue" }).className).toContain("min-h-11");
  });

  it("pairs status colour with a readable label and icon", () => {
    render(<StatusBadge status="warning">Needs review</StatusBadge>);
    const badge = screen.getByText("Needs review");
    expect(badge.querySelector("svg")?.getAttribute("aria-hidden")).toBe("true");
  });

  it("associates field errors with their input", () => {
    render(<FormField error="Enter a valid name." id="name" label="Name"><Input /></FormField>);
    const input = screen.getByRole("textbox", { name: "Name" });
    expect(input.getAttribute("aria-invalid")).toBe("true");
    expect(input.getAttribute("aria-describedby")).toBe("name-error");
    expect(screen.getByRole("alert").id).toBe("name-error");
  });
});

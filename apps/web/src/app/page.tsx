import Link from "next/link";

export default function Home() {
  return (
    <main className="landing-shell">
      <nav className="site-nav">
        <Link className="wordmark" href="/">
          Dolved<span>.</span>
        </Link>
        <div>
          <Link className="text-link" href="/login">
            Sign in
          </Link>
          <Link className="nav-button" href="/register">
            Get started
          </Link>
        </div>
      </nav>

      <section className="hero">
        <p className="eyebrow">Your knowledge, ready when you are</p>
        <h1>
          Stop hunting.
          <br />
          Start knowing.
        </h1>
        <p className="hero-copy">
          Dolved turns the documents your team already trusts into clear,
          grounded answers—with the source always in sight.
        </p>
        <div className="hero-actions">
          <Link className="primary-button" href="/register">
            Build your workspace
          </Link>
          <Link className="secondary-button" href="/login">
            I already have an account
          </Link>
        </div>
      </section>

      <section className="proof-strip" aria-label="Product principles">
        <div>
          <span>01</span>
          <strong>Grounded</strong>
          <p>Answers stay connected to their evidence.</p>
        </div>
        <div>
          <span>02</span>
          <strong>Private</strong>
          <p>Your workspace remains your workspace.</p>
        </div>
        <div>
          <span>03</span>
          <strong>Useful</strong>
          <p>Designed for the question you have right now.</p>
        </div>
      </section>
    </main>
  );
}

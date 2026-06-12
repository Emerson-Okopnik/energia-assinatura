const { useState } = React;

function Header() {
  const [open, setOpen] = useState(false);
  const links = ['Sobre nós', 'Soluções', 'FAQ', 'Contato'];
  return (
    <header style={headerStyles.wrap}>
      <div style={headerStyles.inner}>
        <img src="../../assets/logo-color.png" alt="Líder Energy" style={headerStyles.logo} />
        <nav style={headerStyles.nav}>
          {links.map(l => (
            <a key={l} href="#" style={headerStyles.link}>{l.toUpperCase()}</a>
          ))}
        </nav>
        <a href="#" style={headerStyles.cta}>Fale no WhatsApp
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
      </div>
    </header>
  );
}

const headerStyles = {
  wrap: { position: 'sticky', top: 0, zIndex: 40, background: 'rgba(250,246,241,0.85)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--color-mist)' },
  inner: { maxWidth: 'var(--max-w-content)', margin: '0 auto', padding: '14px 40px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 32 },
  logo: { height: 40 },
  nav: { display: 'flex', gap: 28 },
  link: { fontFamily: 'var(--font-body)', fontWeight: 700, fontSize: 13, letterSpacing: '0.12em', color: 'var(--color-ink)', textDecoration: 'none' },
  cta: { fontFamily: 'var(--font-body)', fontWeight: 700, fontSize: 12, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'white', background: 'var(--color-primary)', padding: '10px 18px', borderRadius: 999, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: 'var(--shadow-glow)' },
};

window.Header = Header;

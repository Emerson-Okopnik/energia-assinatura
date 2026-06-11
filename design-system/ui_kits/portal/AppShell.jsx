const { useState: useStatePortal } = React;

function AppShell({ children, active='dashboard', onNav }) {
  const nav = [
    {id:'dashboard', t:'Painel', icon: <path d="M3 12l9-9 9 9M5 10v10h14V10"/>},
    {id:'bills', t:'Faturas', icon: <><path d="M6 2h12v20H6zM9 7h6M9 11h6M9 15h4"/></>},
    {id:'plant', t:'Minha usina', icon: <><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/></>},
    {id:'settings', t:'Ajustes', icon: <><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></>},
  ];
  return (
    <div style={shellStyles.root}>
      <aside style={shellStyles.sidebar}>
        <img src="../../assets/logo-color.png" alt="Líder Energy" style={{height:36, marginBottom:32, alignSelf:'flex-start'}}/>
        <nav style={{display:'flex', flexDirection:'column', gap:4}}>
          {nav.map(n => {
            const is = active === n.id;
            return (
              <button key={n.id} onClick={()=>onNav && onNav(n.id)} style={{...shellStyles.navBtn, ...(is?shellStyles.navBtnActive:{})}}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">{n.icon}</svg>
                {n.t}
              </button>
            );
          })}
        </nav>
        <div style={{marginTop:'auto'}}>
          <div style={shellStyles.helpCard}>
            <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:14, color:'var(--color-ink)'}}>Precisa de ajuda?</div>
            <div style={{fontFamily:'var(--font-body)', fontSize:12, color:'var(--color-graphite)', lineHeight:1.5, margin:'4px 0 10px'}}>Fale com um especialista pelo WhatsApp.</div>
            <a href="#" style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.08em', textTransform:'uppercase', color:'white', background:'var(--color-primary)', padding:'8px 14px', borderRadius:999, textDecoration:'none', display:'inline-block'}}>Abrir chat</a>
          </div>
        </div>
      </aside>
      <div style={shellStyles.main}>
        <header style={shellStyles.topbar}>
          <div>
            <div style={{fontFamily:'var(--font-body)', fontSize:12, color:'var(--color-graphite)'}}>Bem-vinda de volta,</div>
            <div style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:20, color:'var(--color-ink)'}}>Maria Silva</div>
          </div>
          <div style={{display:'flex', alignItems:'center', gap:14}}>
            <button style={shellStyles.iconBtn}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
            </button>
            <div style={{width:36, height:36, borderRadius:'50%', background:'var(--grad-sun)', color:'white', display:'flex', alignItems:'center', justifyContent:'center', fontFamily:'var(--font-display)', fontWeight:800, fontSize:14}}>MS</div>
          </div>
        </header>
        <div style={{padding:'28px 36px'}}>{children}</div>
      </div>
    </div>
  );
}

const shellStyles = {
  root: { display: 'grid', gridTemplateColumns: '240px 1fr', minHeight: '100vh', background: 'var(--color-linen)' },
  sidebar: { background: 'white', padding: 24, display: 'flex', flexDirection: 'column', borderRight: '1px solid var(--color-mist)' },
  navBtn: { display:'flex', alignItems:'center', gap:10, background:'none', border:'none', padding:'10px 12px', borderRadius:12, fontFamily:'var(--font-body)', fontWeight:600, fontSize:14, color:'var(--color-graphite)', cursor:'pointer', textAlign:'left' },
  navBtnActive: { background: 'rgba(243,147,37,0.10)', color: 'var(--color-primary-deep)', fontWeight: 700 },
  helpCard: { background:'var(--color-linen)', borderRadius:16, padding:14 },
  main: { display: 'flex', flexDirection: 'column' },
  topbar: { display:'flex', alignItems:'center', justifyContent:'space-between', padding:'18px 36px', background:'rgba(255,255,255,0.75)', backdropFilter:'blur(16px)', WebkitBackdropFilter:'blur(16px)', borderBottom:'1px solid var(--color-mist)', position:'sticky', top:0, zIndex:10 },
  iconBtn: { width:36, height:36, borderRadius:10, border:'1px solid var(--color-mist)', background:'white', color:'var(--color-ink)', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center' },
};

window.AppShell = AppShell;

function HowItWorks() {
  const steps = [
    {n:'1', t:'Usinas disponibilizam sua geração de energia.', icon:(<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>)},
    {n:'2', t:'Conectamos essa energia a consumidores que buscam economia.', icon:(<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 5l7 7-7 7M3 5v14"/></svg>)},
    {n:'3', t:'Você, como consumidor, começa a economizar.', icon:(<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12l9-9 9 9M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>)},
    {n:'4', t:'Ou você, como investidor, começa a lucrar com seu ativo.', icon:(<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>)},
  ];
  return (
    <section style={{padding:'80px 40px', background:'white'}}>
      <div style={{maxWidth:'var(--max-w-content)', margin:'0 auto'}}>
        <div style={{textAlign:'center', marginBottom: 56}}>
          <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:13, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-deep)'}}>Como funciona</span>
          <h2 style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:'clamp(28px,3.4vw,40px)', color:'var(--color-ink)', margin:'12px 0 0', letterSpacing:'-0.015em'}}>Quatro passos, sem burocracia.</h2>
        </div>
        <div style={{display:'grid', gridTemplateColumns:'repeat(4, 1fr)', gap:20, position:'relative'}}>
          {steps.map((s,i)=>(
            <div key={s.n} style={{display:'flex', flexDirection:'column', gap:14}}>
              <div style={{width:72, height:72, borderRadius:24, background:'var(--grad-sun)', color:'white', display:'flex', alignItems:'center', justifyContent:'center', boxShadow:'var(--shadow-sm)'}}>
                {s.icon}
              </div>
              <div style={{fontFamily:'var(--font-display)', fontWeight:900, fontSize:36, color:'var(--color-primary-deep)', lineHeight:1}}>{s.n}.</div>
              <p style={{fontFamily:'var(--font-body)', fontSize:15, color:'var(--color-ink)', fontWeight:600, lineHeight:1.45, margin:0}}>{s.t}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

window.HowItWorks = HowItWorks;

function Footer() {
  return (
    <>
      <section style={{padding:'48px 40px', background:'var(--grad-sun)'}}>
        <div style={{maxWidth:'var(--max-w-content)', margin:'0 auto', display:'flex', alignItems:'center', justifyContent:'space-between', gap:24, flexWrap:'wrap'}}>
          <div>
            <div style={{fontFamily:'var(--font-display)', fontWeight:900, fontSize:28, color:'white', lineHeight:1.15, letterSpacing:'-0.015em'}}>Pronto para começar a economizar?</div>
            <div style={{fontFamily:'var(--font-body)', fontSize:15, color:'rgba(255,255,255,0.9)', marginTop:6}}>Fale com um especialista e receba uma simulação sem compromisso.</div>
          </div>
          <a href="#" style={{fontFamily:'var(--font-body)', fontWeight:800, fontSize:13, letterSpacing:'0.08em', textTransform:'uppercase', background:'white', color:'var(--color-primary-deep)', padding:'14px 26px', borderRadius:999, textDecoration:'none', boxShadow:'var(--shadow-md)'}}>
            Falar no WhatsApp →
          </a>
        </div>
      </section>
      <footer style={{padding:'48px 40px 32px', background:'var(--color-ink)', color:'rgba(255,255,255,0.85)'}}>
        <div style={{maxWidth:'var(--max-w-content)', margin:'0 auto', display:'grid', gridTemplateColumns:'1.4fr 1fr 1fr', gap:32, alignItems:'flex-start'}}>
          <div>
            <img src="../../assets/logo-white.png" alt="Líder Energy" style={{height:42, marginBottom:16}}/>
            <div style={{fontFamily:'var(--font-body)', fontSize:13, lineHeight:1.7, maxWidth:360}}>
              Consórcio que conecta consumidores e usinas no modelo de energia por assinatura.
            </div>
          </div>
          <div>
            <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-warm)', marginBottom:12}}>Contato</div>
            <div style={{fontFamily:'var(--font-body)', fontSize:13, lineHeight:1.8}}>
              contato@consorcioliderenergy.com.br<br/>
              +55 (47) 99709-7976
            </div>
          </div>
          <div>
            <div style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:11, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-warm)', marginBottom:12}}>Siga-nos</div>
            <div style={{display:'flex', gap:10}}>
              {['Instagram','LinkedIn','Facebook'].map(s=>(
                <a key={s} href="#" style={{fontFamily:'var(--font-body)', fontSize:12, fontWeight:600, color:'white', background:'rgba(255,255,255,0.1)', padding:'8px 14px', borderRadius:999, textDecoration:'none'}}>{s}</a>
              ))}
            </div>
          </div>
        </div>
        <div style={{maxWidth:'var(--max-w-content)', margin:'32px auto 0', paddingTop:20, borderTop:'1px solid rgba(255,255,255,0.1)', fontFamily:'var(--font-mono)', fontSize:11, color:'rgba(255,255,255,0.55)'}}>
          Liberdade Energia Consórcio De Consumidores De Energia Elétrica — CNPJ 58.750.788/0001-33 — Todos os Direitos Reservados
        </div>
      </footer>
    </>
  );
}

window.Footer = Footer;

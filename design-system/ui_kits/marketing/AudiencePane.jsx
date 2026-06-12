function AudiencePane() {
  return (
    <section style={{padding: '80px 40px', background: 'var(--color-linen)'}}>
      <div style={{maxWidth:'var(--max-w-content)', margin:'0 auto'}}>
        <div style={{textAlign:'center', marginBottom: 48}}>
          <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:13, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-deep)'}}>Sobre nós</span>
          <h2 style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:'clamp(28px,3.4vw,40px)', lineHeight:1.15, color:'var(--color-ink)', margin:'12px auto 14px', maxWidth:720, letterSpacing:'-0.015em'}}>
            Um consórcio que conecta consumidores e usinas no modelo de energia por assinatura.
          </h2>
          <p style={{fontFamily:'var(--font-body)', fontSize:16, color:'var(--color-graphite)', maxWidth:560, margin:'0 auto', lineHeight:1.6}}>
            Economia na conta de luz para quem consome. Rentabilidade recorrente para quem gera.
          </p>
        </div>
        <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:24}}>
          <Pane
            accent="var(--color-accent-leaf)"
            tint="rgba(95,181,58,0.08)"
            eyebrow="Para consumidores finais"
            title="Economize até 28% na sua conta de luz sem precisar instalar nada."
            bullets={['Sem custo de instalação','Sem obras ou equipamentos','Redução real na fatura','Energia limpa e legal']}
            tagline="Indicado para moradores de condomínio, pequenas e médias empresas, e quem quer economizar de forma sustentável."
          />
          <Pane
            accent="var(--color-primary)"
            tint="rgba(243,147,37,0.08)"
            eyebrow="Para usineiros e investidores"
            title="Transforme sua usina solar em fonte de receita previsível e recorrente."
            bullets={['Arrendamento da geração','Gestão completa do consórcio','Receita passiva e previsível','Segurança jurídica (Lei 14.300/22)']}
            tagline="Para investidores do setor, agropecuaristas com geração excedente, fundos ESG e proprietários de usinas."
          />
        </div>
      </div>
    </section>
  );
}

function Pane({ accent, tint, eyebrow, title, bullets, tagline }) {
  return (
    <div style={{background:'white', borderRadius:24, padding:32, boxShadow:'var(--shadow-sm)', display:'flex', flexDirection:'column', gap:16, position:'relative', overflow:'hidden'}}>
      <div style={{position:'absolute', top:-60, right:-60, width:200, height:200, background:tint, borderRadius:'50%'}}/>
      <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:12, letterSpacing:'0.14em', textTransform:'uppercase', color:accent, position:'relative'}}>{eyebrow}</span>
      <h3 style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:24, lineHeight:1.2, color:'var(--color-ink)', margin:0, position:'relative', letterSpacing:'-0.01em'}}>{title}</h3>
      <ul style={{listStyle:'none', padding:0, margin:0, display:'flex', flexDirection:'column', gap:10, position:'relative'}}>
        {bullets.map(b => (
          <li key={b} style={{display:'flex', alignItems:'center', gap:10, fontFamily:'var(--font-body)', fontSize:15, color:'var(--color-ink)'}}>
            <span style={{width:22, height:22, borderRadius:'50%', background:tint, color:accent, display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0}}>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            {b}
          </li>
        ))}
      </ul>
      <p style={{fontFamily:'var(--font-body)', fontSize:13, color:'var(--color-graphite)', lineHeight:1.55, margin:0, position:'relative', paddingTop:8, borderTop:'1px solid var(--color-mist)'}}>{tagline}</p>
      <a href="#" style={{position:'relative', alignSelf:'flex-start', fontFamily:'var(--font-body)', fontWeight:700, fontSize:12, letterSpacing:'0.08em', textTransform:'uppercase', color:'white', background:accent, padding:'12px 22px', borderRadius:999, textDecoration:'none'}}>
            Converse com um especialista →
      </a>
    </div>
  );
}

window.AudiencePane = AudiencePane;

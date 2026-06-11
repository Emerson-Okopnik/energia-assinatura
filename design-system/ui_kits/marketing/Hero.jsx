const { useState: useStateHero } = React;

function Hero() {
  const [audience, setAudience] = useStateHero('consumidor');
  return (
    <section style={heroStyles.wrap}>
      <div style={heroStyles.bg} />
      <div style={heroStyles.inner}>
        <div style={heroStyles.left}>
          <span style={heroStyles.eyebrow}>Energia por assinatura</span>
          <h1 style={heroStyles.h1}>
            Energia solar <span style={{color:'var(--color-primary)'}}>inteligente</span>,<br/>
            para quem quer <em style={{fontStyle:'normal',color:'var(--color-accent-leaf-deep)'}}>economizar</em> ou <em style={{fontStyle:'normal',color:'var(--color-primary-deep)'}}>rentabilizar</em>.
          </h1>
          <p style={heroStyles.lead}>
            Conectamos consumidores e usinas no modelo de energia por assinatura — com economia real, rentabilidade previsível e segurança jurídica.
          </p>
          <div style={heroStyles.stats}>
            <Stat num="28%" label="economia média na conta de luz" />
            <div style={heroStyles.divider} />
            <Stat num="0" label="instalação, obra ou equipamento" />
            <div style={heroStyles.divider} />
            <Stat num="Lei 14.300/22" label="totalmente regulamentado" mono />
          </div>
        </div>
        <div style={heroStyles.right}>
          <LeadForm audience={audience} setAudience={setAudience} />
        </div>
      </div>
    </section>
  );
}

function Stat({ num, label, mono }) {
  return (
    <div>
      <div style={{fontFamily: mono ? 'var(--font-mono)' : 'var(--font-display)', fontWeight: mono?700:900, fontSize: mono?14:28, color: 'var(--color-ink)', lineHeight: 1}}>{num}</div>
      <div style={{fontFamily: 'var(--font-body)', fontSize: 11, color: 'var(--color-graphite)', textTransform: 'uppercase', letterSpacing: '0.1em', fontWeight: 600, marginTop: 6, maxWidth: 140}}>{label}</div>
    </div>
  );
}

function LeadForm({ audience, setAudience }) {
  return (
    <div style={heroStyles.card}>
      <div style={heroStyles.cardEyebrow}>Converse com um especialista</div>
      <div style={heroStyles.cardTitle}>Receba uma simulação grátis</div>
      <Input label="Nome" placeholder="Maria Silva" />
      <Input label="CPF / CNPJ" placeholder="000.000.000-00" />
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:10}}>
        <Input label="Telefone" placeholder="(47) 9 9709-7976" />
        <Input label="E-mail" placeholder="voce@email.com" />
      </div>
      <div style={{marginTop: 4}}>
        <div style={heroStyles.label}>Eu sou…</div>
        <div style={heroStyles.segmented}>
          {[
            {id:'consumidor', t:'Consumidor', s:'quero economizar'},
            {id:'usineiro', t:'Usineiro', s:'quero rentabilizar'},
          ].map(o => (
            <button key={o.id} onClick={()=>setAudience(o.id)} style={{...heroStyles.segBtn, ...(audience===o.id?heroStyles.segBtnActive:{})}}>
              <div style={{fontWeight:800}}>{o.t}</div>
              <div style={{fontSize:11,opacity:0.75,fontWeight:600}}>{o.s}</div>
            </button>
          ))}
        </div>
      </div>
      <button style={heroStyles.submit}>Enviar →</button>
      <div style={heroStyles.fine}>Ao enviar, você concorda em ser contatado pela equipe Líder Energy.</div>
    </div>
  );
}

function Input({ label, placeholder }) {
  return (
    <label style={{display:'flex',flexDirection:'column',gap:5}}>
      <span style={heroStyles.label}>{label}</span>
      <input placeholder={placeholder} style={heroStyles.input}
        onFocus={e=>{e.target.style.borderColor='var(--color-primary)'; e.target.style.boxShadow='0 0 0 3px rgba(243,147,37,0.2)';}}
        onBlur={e=>{e.target.style.borderColor='var(--color-mist)'; e.target.style.boxShadow='none';}}
      />
    </label>
  );
}

const heroStyles = {
  wrap: { position: 'relative', padding: '80px 40px 100px', overflow: 'hidden' },
  bg: { position: 'absolute', top: -200, left: -200, width: 600, height: 600, background: 'radial-gradient(circle, rgba(243,147,37,0.22), transparent 60%)', pointerEvents: 'none' },
  inner: { position: 'relative', maxWidth: 'var(--max-w-content)', margin: '0 auto', display: 'grid', gridTemplateColumns: '1.15fr 1fr', gap: 60, alignItems: 'center' },
  left: { display: 'flex', flexDirection: 'column', gap: 20 },
  eyebrow: { fontFamily: 'var(--font-body)', fontWeight: 700, fontSize: 13, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--color-primary-deep)' },
  h1: { fontFamily: 'var(--font-display)', fontWeight: 900, fontSize: 'clamp(36px,4.4vw,56px)', lineHeight: 1.05, letterSpacing: '-0.02em', color: 'var(--color-ink)', margin: 0 },
  lead: { fontFamily: 'var(--font-body)', fontSize: 18, lineHeight: 1.55, color: 'var(--color-graphite)', maxWidth: 520, margin: 0 },
  stats: { display: 'flex', alignItems: 'flex-start', gap: 20, marginTop: 12 },
  divider: { width: 1, alignSelf: 'stretch', background: 'var(--color-mist)' },
  right: { display: 'flex', justifyContent: 'center' },
  card: { background: 'white', borderRadius: 24, padding: 26, boxShadow: 'var(--shadow-lg)', display: 'flex', flexDirection: 'column', gap: 12, width: '100%', maxWidth: 420 },
  cardEyebrow: { fontFamily: 'var(--font-body)', fontWeight: 700, fontSize: 11, letterSpacing: '0.14em', textTransform: 'uppercase', color: 'var(--color-primary-deep)' },
  cardTitle: { fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 22, color: 'var(--color-ink)', marginBottom: 4 },
  label: { fontFamily: 'var(--font-body)', fontSize: 12, fontWeight: 600, color: 'var(--color-graphite)' },
  input: { fontFamily: 'var(--font-body)', fontSize: 14, padding: '11px 14px', border: '1.5px solid var(--color-mist)', borderRadius: 12, background: 'white', color: 'var(--color-ink)', outline: 'none', transition: 'all .15s' },
  segmented: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 6 },
  segBtn: { padding: 12, border: '1.5px solid var(--color-mist)', background: 'white', borderRadius: 12, textAlign: 'left', cursor: 'pointer', fontFamily: 'var(--font-body)', color: 'var(--color-ink)' },
  segBtnActive: { borderColor: 'var(--color-primary)', background: 'rgba(243,147,37,0.06)' },
  submit: { marginTop: 6, fontFamily: 'var(--font-body)', fontWeight: 800, fontSize: 14, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'white', background: 'var(--color-primary)', border: 'none', padding: '14px', borderRadius: 999, cursor: 'pointer', boxShadow: 'var(--shadow-glow)' },
  fine: { fontFamily: 'var(--font-body)', fontSize: 11, color: 'var(--color-slate)', textAlign: 'center', lineHeight: 1.5 },
};

window.Hero = Hero;

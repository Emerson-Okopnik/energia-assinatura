const { useState: useStateFAQ } = React;

function FAQ() {
  const items = [
    { q:'O que é energia por assinatura?', a:'Energia solar por assinatura é um serviço em que o consumidor assina um plano para receber créditos de energia limpa, gerada em usinas solares, diretamente na conta de luz — sem precisar instalar placas no imóvel.'},
    { q:'Preciso instalar algo no imóvel para participar?', a:'Não é necessário instalar placas solares ou fazer qualquer obra no imóvel. Toda a energia é gerada em usinas parceiras e os créditos são compensados automaticamente na sua conta de luz pela distribuidora local.'},
    { q:'Como funciona o consórcio para quem tem uma usina?', a:'Você arrenda parte ou toda a capacidade da sua usina para o consórcio. Em troca, recebe uma receita recorrente previsível, com gestão completa feita pela Líder Energy e segurança jurídica pela Lei 14.300/22.'},
    { q:'Qual é a economia na conta de luz?', a:'A economia pode chegar a até 29% do valor da energia consumida. Os descontos são garantidos em contrato.'},
    { q:'É legal e regulamentado?', a:'Sim. O consórcio de energia solar por assinatura é regulamentado pela ANEEL (Agência Nacional de Energia Elétrica), que trata da geração distribuída e do sistema de compensação de energia elétrica no Brasil.'},
  ];
  const [open, setOpen] = useStateFAQ(0);
  return (
    <section style={{padding:'80px 40px', background:'var(--color-linen)'}}>
      <div style={{maxWidth:800, margin:'0 auto'}}>
        <div style={{textAlign:'center', marginBottom: 40}}>
          <span style={{fontFamily:'var(--font-body)', fontWeight:700, fontSize:13, letterSpacing:'0.14em', textTransform:'uppercase', color:'var(--color-primary-deep)'}}>Perguntas frequentes</span>
          <h2 style={{fontFamily:'var(--font-display)', fontWeight:800, fontSize:'clamp(28px,3.4vw,40px)', color:'var(--color-ink)', margin:'12px 0 0', letterSpacing:'-0.015em'}}>Tire suas dúvidas</h2>
        </div>
        <div style={{display:'flex', flexDirection:'column', gap:10}}>
          {items.map((it,i)=>{
            const isOpen = open===i;
            return (
              <div key={i} style={{background:'white', borderRadius:16, boxShadow: isOpen ? 'var(--shadow-md)':'var(--shadow-xs)', overflow:'hidden', transition:'box-shadow .2s'}}>
                <button onClick={()=>setOpen(isOpen?-1:i)} style={{width:'100%', display:'flex', alignItems:'center', justifyContent:'space-between', gap:16, padding:'18px 22px', background:'none', border:'none', cursor:'pointer', textAlign:'left'}}>
                  <span style={{fontFamily:'var(--font-display)', fontWeight:700, fontSize:17, color:'var(--color-ink)'}}>{it.q}</span>
                  <span style={{width:32, height:32, borderRadius:'50%', background: isOpen?'var(--color-primary)':'var(--color-primary-soft)', color: isOpen?'white':'var(--color-primary-deep)', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0, transition:'all .2s', transform: isOpen?'rotate(45deg)':'rotate(0deg)'}}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
                  </span>
                </button>
                {isOpen && (
                  <div style={{padding:'0 22px 22px', fontFamily:'var(--font-body)', fontSize:15, lineHeight:1.6, color:'var(--color-graphite)'}}>{it.a}</div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

window.FAQ = FAQ;

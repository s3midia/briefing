<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$clientToken = strtolower(trim((string) ($_GET['c'] ?? '')));
$client = client_by_token($clientToken);
if (!$client) {
    app_error_page('Link inválido', 'Peça à S3 Mídia um novo link individual para preencher o briefing.', 404);
}
if (($client['status'] ?? '') === 'concluido') {
    app_error_page('Briefing já recebido', 'Obrigado, ' . $client['nome'] . '. Suas respostas já foram enviadas com sucesso.', 409);
}

$clientName = (string) $client['nome'];
$storageKey = 's3-briefing-' . hash('sha256', $clientToken) . '-v2';
$formError = (string) ($_SESSION['briefing_form_error'] ?? '');
$formErrorField = $_SESSION['briefing_form_error_field'] ?? null;
unset($_SESSION['briefing_form_error'], $_SESSION['briefing_form_error_field']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#0a0a0a" />
  <title>Briefing Estratégico | <?= e($clientName) ?> • S3 Mídia</title>
  <style>
    :root {
      --bg: #f4f4f2;
      --card: #ffffff;
      --ink: #111111;
      --muted: #666866;
      --line: #deded9;
      --soft: #efefeb;
      --black: #050505;
      --radius: 22px;
      --shadow: 0 18px 60px rgba(0,0,0,.08);
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at 10% 10%, rgba(0,0,0,.045), transparent 28%),
        linear-gradient(180deg, #f7f7f4 0%, var(--bg) 100%);
      min-height: 100vh;
    }
    .shell { width: min(1080px, calc(100% - 28px)); margin: 0 auto; }
    .topbar {
      padding: 26px 0 18px;
      display: flex; align-items: center; justify-content: space-between; gap: 16px;
    }
    .brand { display:flex; align-items:center; gap:13px; }
    .brand-mark {
      width: 52px; height: 52px; border-radius: 16px; background: var(--black);
      display:grid; place-items:center; overflow:hidden; padding: 5px;
      box-shadow: 0 10px 26px rgba(0,0,0,.18);
    }
    .brand-mark img { width: 100%; height: 100%; object-fit: contain; filter: invert(1); }
    .brand-name { font-weight: 780; letter-spacing: -.02em; line-height: 1.05; }
    .brand-name small { display:block; color: var(--muted); font-size: 11px; font-weight: 620; letter-spacing:.04em; margin-top:4px; }
    .badge {
      font-size: 12px; font-weight: 700; letter-spacing: .02em;
      border: 1px solid var(--line); background: rgba(255,255,255,.72);
      padding: 9px 12px; border-radius: 999px;
    }

    .hero {
      background: var(--black); color: #fff; border-radius: 30px; padding: 42px;
      position: relative; overflow: hidden; box-shadow: var(--shadow);
    }
    .hero::after {
      content:""; position:absolute; width:300px; height:300px; border-radius:50%;
      right:-120px; top:-140px; border: 44px solid rgba(255,255,255,.065);
    }
    .eyebrow { font-size:12px; font-weight:760; letter-spacing:.12em; text-transform:uppercase; color:#cfcfcb; }
    h1 { margin: 10px 0 12px; font-size: clamp(34px, 6vw, 64px); line-height:.98; letter-spacing:-.055em; max-width: 820px; }
    .hero p { margin:0; max-width: 720px; color:#d8d8d3; font-size:16px; line-height:1.6; }
    .hero-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:24px; }
    .hero-meta span { border:1px solid rgba(255,255,255,.18); border-radius:999px; padding:8px 11px; font-size:12px; color:#ededeb; }

    .progress-wrap { margin: 24px 0 18px; }
    .progress-top { display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:12px; color:var(--muted); margin-bottom:9px; }
    .progress-track { height: 7px; background:#deded9; border-radius:999px; overflow:hidden; }
    .progress-bar { width:12.5%; height:100%; background:#111; border-radius:999px; transition: width .35s ease; }

    .form-card {
      background: var(--card); border:1px solid rgba(0,0,0,.055); border-radius: var(--radius);
      box-shadow: var(--shadow); overflow:hidden; margin-bottom: 34px;
    }
    form { margin:0; }
    .step { display:none; padding: 38px; animation:fade .24s ease; }
    .step.active { display:block; }
    @keyframes fade { from { opacity:.25; transform: translateY(4px); } to { opacity:1; transform:none; } }
    .step-header { display:flex; align-items:flex-start; gap:16px; margin-bottom:30px; }
    .step-num {
      min-width: 42px; height:42px; border-radius:13px; display:grid; place-items:center;
      background:#111; color:#fff; font-weight:800; font-size:13px;
    }
    .step-header h2 { margin:0 0 5px; font-size:26px; letter-spacing:-.03em; }
    .step-header p { margin:0; color:var(--muted); line-height:1.55; font-size:14px; }

    .grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:18px; }
    .full { grid-column: 1 / -1; }
    .field { display:flex; flex-direction:column; gap:8px; }
    label, legend { font-size:13px; font-weight:720; line-height:1.35; }
    .hint { font-size:11.5px; color:#7a7c79; margin-top:-3px; }
    input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="number"], textarea, select {
      width:100%; border:1px solid var(--line); border-radius:14px; padding:13px 14px;
      background:#fbfbf9; color:var(--ink); font:inherit; outline:none;
      transition: border-color .16s ease, box-shadow .16s ease, background .16s ease;
    }
    textarea { resize:vertical; min-height:104px; line-height:1.45; }
    input:focus, textarea:focus, select:focus { border-color:#111; box-shadow:0 0 0 3px rgba(0,0,0,.06); background:#fff; }
    fieldset { border:0; padding:0; margin:0; }
    .choices { display:flex; flex-wrap:wrap; gap:9px; margin-top:8px; }
    .choice { position:relative; }
    .choice input { position:absolute; opacity:0; pointer-events:none; }
    .choice span {
      display:block; border:1px solid var(--line); background:#fbfbf9; border-radius:999px; padding:10px 13px;
      font-size:12.5px; cursor:pointer; user-select:none; transition:.15s ease;
    }
    .choice input:checked + span { background:#111; color:#fff; border-color:#111; }
    .divider { height:1px; background:var(--soft); grid-column:1/-1; margin: 4px 0; }
    .conditional { display:none; }
    .conditional.show { display:flex; }

    .nav {
      display:flex; justify-content:space-between; gap:12px; padding:22px 38px;
      border-top:1px solid var(--soft); background:#fafaf8;
    }
    button {
      border:0; border-radius:14px; padding:13px 18px; font-weight:760; font-size:14px;
      cursor:pointer; transition:transform .12s ease, opacity .12s ease, box-shadow .12s ease;
    }
    button:hover { transform: translateY(-1px); }
    .btn-ghost { background:#ecece8; color:#222; }
    .btn-main { margin-left:auto; background:#090909; color:#fff; box-shadow:0 8px 20px rgba(0,0,0,.14); }
    .btn-send { background:#090909; color:#fff; min-width: 190px; }
    .btn-send:disabled { opacity:.55; cursor:not-allowed; transform:none; }
    .save-note { font-size:11.5px; color:#80827f; align-self:center; margin-right:auto; }

    .consent {
      border:1px solid var(--line); border-radius:16px; padding:16px; background:#fafaf8;
      display:flex; gap:11px; align-items:flex-start;
    }
    .consent input { margin-top:3px; }
    .consent label { font-weight:560; color:#555; }
    .micro { font-size:11px; color:#81827f; line-height:1.45; }

    .error {
      display:none; margin: 0 38px 18px; background:#fff3f1; color:#7d241e; border:1px solid #f0c8c3;
      border-radius:13px; padding:11px 13px; font-size:12px;
    }
    .error.show { display:block; }
    .server-error-focus { outline:3px solid #d65b4f; outline-offset:3px; }
    .required-error { border-color:#cc463d !important; box-shadow:0 0 0 3px rgba(204,70,61,.16) !important; animation:requiredPulse 1.15s ease-in-out 2; }
    .required-hint { display:block; margin-top:6px; color:#b5352d; font-size:12px; font-weight:750; }
    .consent.required-error { outline:3px solid #cc463d; outline-offset:2px; }
    @keyframes requiredPulse { 0%,100%{box-shadow:0 0 0 3px rgba(204,70,61,.14)} 50%{box-shadow:0 0 0 7px rgba(204,70,61,.28)} }

    footer { padding: 0 0 38px; text-align:center; color:#737572; font-size:11px; }
    footer strong { color:#111; }

    @media (max-width: 720px) {
      .shell { width:min(100% - 18px, 1080px); }
      .topbar { padding-top:16px; }
      .badge { display:none; }
      .hero { padding:28px 22px; border-radius:24px; }
      .hero p { font-size:14px; }
      .step { padding:26px 20px; }
      .nav { padding:18px 20px; position:sticky; bottom:0; z-index:5; }
      .grid { grid-template-columns:1fr; gap:16px; }
      .full { grid-column:auto; }
      .divider { grid-column:auto; }
      .step-header h2 { font-size:22px; }
      .save-note { display:none; }
      .btn-send { min-width:0; }
      .error { margin:0 20px 14px; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark"><img alt="Símbolo S3 Mídia" src="data:image/png;base64,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"></div>
        <div class="brand-name">S3 Mídia<small>MARKETING & PUBLICIDADE</small></div>
      </div>
      <div class="badge">Briefing confidencial</div>
    </header>

    <section class="hero">
      <div class="eyebrow">Diagnóstico estratégico • 2026</div>
      <h1><?= e($clientName) ?>,<br>vamos construir sua marca profissional.</h1>
      <p>Este briefing reúne as informações essenciais para a S3 Mídia desenvolver seu posicionamento, identidade visual, presença digital, site e estratégia de marketing de forma coerente com seus objetivos profissionais.</p>
      <div class="hero-meta">
        <span>⏱ 10–15 minutos</span>
        <span>✓ Respostas salvas neste dispositivo</span>
        <span>→ Pensado para celular</span>
      </div>
    </section>

    <div class="progress-wrap">
      <div class="progress-top">
        <span id="stepLabel">Etapa 1 de 8</span>
        <span id="percentLabel">12%</span>
      </div>
      <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
    </div>

    <div class="form-card">
      <form name="briefing-s3" method="POST" action="/salvar.php" id="briefingForm">
        <input type="hidden" name="client_token" value="<?= e($clientToken) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token('public')) ?>">
        <p hidden><label>Não preencha: <input name="bot-field"></label></p>

        <!-- 1 -->
        <section class="step active" data-step="1">
          <div class="step-header">
            <div class="step-num">01</div>
            <div><h2>Perfil profissional</h2><p>Começamos pela sua história e pelo momento atual da carreira.</p></div>
          </div>
          <div class="grid">
            <div class="field"><label for="nome">Nome completo</label><input id="nome" name="Nome completo" type="text" value="<?= e($clientName) ?>"></div>
            <div class="field"><label for="nome_prof">Nome profissional desejado</label><input id="nome_prof" name="Nome profissional" type="text" placeholder="Ex.: <?= e($clientName) ?> Advocacia"></div>
            <div class="field"><label for="cidade">Cidade / região de atuação *</label><input id="cidade" name="Cidade ou região" type="text" required></div>
            <div class="field"><label for="oab">OAB</label><input id="oab" name="OAB" type="text" placeholder="Informe se já estiver disponível"></div>
            <div class="field"><label for="faculdade">Faculdade e ano de formação</label><input id="faculdade" name="Formação" type="text"></div>
            <div class="field"><label for="contato">WhatsApp / telefone</label><input id="contato" name="Contato" type="tel"></div>
            <div class="field full"><label for="historia">Conte brevemente sua trajetória até a advocacia *</label><textarea id="historia" name="Histórico profissional" placeholder="Formação, estágios, experiências, decisões de carreira e momentos importantes." required></textarea></div>
            <div class="field full"><label for="experiencia">Experiências profissionais ou jurídicas relevantes</label><textarea id="experiencia" name="Experiências relevantes" placeholder="Escritórios, empresas, estágios, projetos, áreas em que já teve contato etc."></textarea></div>
          </div>
        </section>

        <!-- 2 -->
        <section class="step" data-step="2">
          <div class="step-header">
            <div class="step-num">02</div>
            <div><h2>Atuação jurídica</h2><p>Definimos foco, especialidades e modelo de atendimento.</p></div>
          </div>
          <div class="grid">
            <fieldset class="field full">
              <legend>Áreas em que pretende atuar</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Áreas de atuação" value="Civil"><span>Civil</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Trabalhista"><span>Trabalhista</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Empresarial"><span>Empresarial</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Tributário"><span>Tributário</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Previdenciário"><span>Previdenciário</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Família e Sucessões"><span>Família e Sucessões</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Imobiliário"><span>Imobiliário</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Agronegócio"><span>Agronegócio</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Consumidor"><span>Consumidor</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Criminal"><span>Criminal</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Contratos"><span>Contratos</span></label><label class="choice"><input type="checkbox" name="Áreas de atuação" value="Outra"><span>Outra</span></label>
              </div>
            </fieldset>
            <div class="field"><label for="principal_area">Principal área / prioridade *</label><input id="principal_area" name="Área principal" type="text" required></div>
            <div class="field"><label for="especializacao">Especialização atual ou planejada</label><input id="especializacao" name="Especialização" type="text"></div>
            <fieldset class="field full">
              <legend>Modelo de atuação desejado</legend>
              <div class="choices">
                <label class="choice"><input type="radio" name="Modelo de atuação" value="Autônomo / marca pessoal"><span>Autônomo / marca pessoal</span></label>
                <label class="choice"><input type="radio" name="Modelo de atuação" value="Escritório próprio"><span>Escritório próprio</span></label>
                <label class="choice"><input type="radio" name="Modelo de atuação" value="Sociedade / parceria"><span>Sociedade / parceria</span></label>
                <label class="choice"><input type="radio" name="Modelo de atuação" value="Ainda definindo"><span>Ainda definindo</span></label>
              </div>
            </fieldset>
            <fieldset class="field full">
              <legend>Formato de atendimento</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Formato de atendimento" value="Presencial"><span>Presencial</span></label>
                <label class="choice"><input type="checkbox" name="Formato de atendimento" value="Online"><span>Online</span></label>
                <label class="choice"><input type="checkbox" name="Formato de atendimento" value="Híbrido"><span>Híbrido</span></label>
                <label class="choice"><input type="checkbox" name="Formato de atendimento" value="Empresas / in company"><span>Empresas / in company</span></label>
              </div>
            </fieldset>
            <div class="field full"><label for="servicos">Quais serviços ou tipos de demanda você deseja priorizar?</label><textarea id="servicos" name="Serviços prioritários"></textarea></div>
          </div>
        </section>

        <!-- 3 -->
        <section class="step" data-step="3">
          <div class="step-header">
            <div class="step-num">03</div>
            <div><h2>Público e mercado</h2><p>Para comunicar bem, precisamos saber exatamente com quem queremos falar.</p></div>
          </div>
          <div class="grid">
            <fieldset class="field full">
              <legend>Quem deseja atender?</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Pessoa física"><span>Pessoa física</span></label>
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Empresas"><span>Empresas</span></label>
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Produtores rurais"><span>Produtores rurais</span></label>
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Profissionais liberais"><span>Profissionais liberais</span></label>
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Famílias"><span>Famílias</span></label>
                <label class="choice"><input type="checkbox" name="Perfil de cliente" value="Outro"><span>Outro</span></label>
              </div>
            </fieldset>
            <div class="field full"><label for="cliente_ideal">Descreva seu cliente ideal *</label><textarea id="cliente_ideal" name="Cliente ideal" placeholder="Perfil, profissão ou segmento, poder de decisão, região, necessidades, tipo de problema que costuma enfrentar." required></textarea></div>
            <div class="field"><label for="regioes">Cidades / regiões que deseja atender</label><input id="regioes" name="Regiões de interesse" type="text"></div>
            <div class="field"><label for="segmentos">Segmentos específicos de interesse</label><input id="segmentos" name="Segmentos de interesse" type="text" placeholder="Ex.: agro, comércio, construção, saúde..."></div>
            <div class="field full"><label for="dores">Quais são os principais problemas que esse público enfrenta e que você pode ajudar a resolver?</label><textarea id="dores" name="Dores do público"></textarea></div>
          </div>
        </section>

        <!-- 4 -->
        <section class="step" data-step="4">
          <div class="step-header">
            <div class="step-num">04</div>
            <div><h2>Posicionamento e identidade</h2><p>Aqui definimos a percepção desejada para sua marca.</p></div>
          </div>
          <div class="grid">
            <fieldset class="field full">
              <legend>Como você gostaria de ser percebido?</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Percepção desejada" value="Sofisticado"><span>Sofisticado</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Moderno"><span>Moderno</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Tradicional"><span>Tradicional</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Próximo"><span>Próximo</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Técnico"><span>Técnico</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Estratégico"><span>Estratégico</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Premium"><span>Premium</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Corporativo"><span>Corporativo</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Inovador"><span>Inovador</span></label><label class="choice"><input type="checkbox" name="Percepção desejada" value="Acessível"><span>Acessível</span></label>
              </div>
            </fieldset>
            <div class="field full"><label for="palavras">Escolha até 5 palavras que deveriam representar sua marca *</label><input id="palavras" name="Palavras da marca" type="text" placeholder="Ex.: confiança, clareza, estratégia, segurança, proximidade" required></div>
            <div class="field full"><label for="primeira_impressao">O que gostaria que uma pessoa pensasse ao encontrar seu perfil pela primeira vez?</label><textarea id="primeira_impressao" name="Primeira impressão desejada"></textarea></div>
            <div class="field"><label for="nome_marca">Nome preferido para a marca</label><select id="nome_marca" name="Nome da marca"><option value="">Selecione</option><option><?= e($clientName) ?></option><option><?= e($clientName) ?> Advocacia</option><option><?= e($clientName) ?> Advocacia e Consultoria</option><option>Outro</option></select></div>
            <div class="field"><label for="estilo">Estilo visual preferido</label><select id="estilo" name="Estilo visual"><option value="">Selecione</option><option>Minimalista</option><option>Clássico</option><option>Sofisticado</option><option>Corporativo</option><option>Moderno</option><option>Sóbrio</option><option>Não sei / quero orientação</option></select></div>
            <div class="field"><label for="cores">Cores que gosta</label><input id="cores" name="Cores preferidas" type="text"></div>
            <div class="field"><label for="evitar">Cores, símbolos ou estilos que deseja evitar</label><input id="evitar" name="Elementos a evitar" type="text"></div>
            <div class="field full"><label for="referencia_visual">Referências visuais que admira</label><textarea id="referencia_visual" name="Referências visuais" placeholder="Pode citar advogados, escritórios ou marcas de outros segmentos. Se possível, cole links."></textarea></div>
          </div>
        </section>

        <!-- 5 -->
        <section class="step" data-step="5">
          <div class="step-header">
            <div class="step-num">05</div>
            <div><h2>Presença digital e site</h2><p>Mapeamos o que já existe e o que precisa ser construído.</p></div>
          </div>
          <div class="grid">
            <div class="field"><label for="instagram">Instagram atual</label><input id="instagram" name="Instagram" type="text" placeholder="@usuario ou link"></div>
            <div class="field"><label for="linkedin">LinkedIn</label><input id="linkedin" name="LinkedIn" type="text" placeholder="Link, se houver"></div>
            <div class="field"><label for="whatsapp_business">Usa WhatsApp Business?</label><select id="whatsapp_business" name="WhatsApp Business"><option value="">Selecione</option><option>Sim</option><option>Não</option><option>Pretendo configurar</option></select></div>
            <div class="field"><label for="google_perfil">Possui Perfil da Empresa no Google?</label><select id="google_perfil" name="Perfil da Empresa no Google"><option value="">Selecione</option><option>Sim</option><option>Não</option><option>Não sei</option></select></div>
            <div class="field"><label for="tem_site">Já possui site?</label><select id="tem_site" name="Possui site" data-toggle="site_url"><option value="">Selecione</option><option>Sim</option><option>Não</option></select></div>
            <div class="field conditional" id="site_url"><label for="site">Endereço do site</label><input id="site" name="Site atual" type="url" placeholder="https://..."></div>
            <div class="field"><label for="dominio">Possui domínio registrado?</label><input id="dominio" name="Domínio" type="text" placeholder="Ex.: seunome.com.br"></div>
            <div class="field"><label for="fotos">Possui fotos profissionais?</label><select id="fotos" name="Fotos profissionais"><option value="">Selecione</option><option>Sim</option><option>Não</option><option>Tenho algumas, mas quero produzir novas</option></select></div>
            <fieldset class="field full">
              <legend>O que espera de um site profissional?</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Objetivos do site" value="Autoridade"><span>Autoridade</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Apresentação profissional"><span>Apresentação profissional</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Ser encontrado no Google"><span>Ser encontrado no Google</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Apresentar áreas de atuação"><span>Apresentar áreas de atuação</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Receber contatos"><span>Receber contatos</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Publicar artigos"><span>Publicar artigos</span></label><label class="choice"><input type="checkbox" name="Objetivos do site" value="Fortalecer marca"><span>Fortalecer marca</span></label>
              </div>
            </fieldset>
          </div>
        </section>

        <!-- 6 -->
        <section class="step" data-step="6">
          <div class="step-header">
            <div class="step-num">06</div>
            <div><h2>Conteúdo e aquisição</h2><p>Entendemos sua disponibilidade para construir presença e autoridade.</p></div>
          </div>
          <div class="grid">
            <fieldset class="field full">
              <legend>Você se sente confortável aparecendo em vídeos?</legend>
              <div class="choices">
                <label class="choice"><input type="radio" name="Conforto em vídeo" value="Sim"><span>Sim</span></label>
                <label class="choice"><input type="radio" name="Conforto em vídeo" value="Talvez, com direção"><span>Talvez, com direção</span></label>
                <label class="choice"><input type="radio" name="Conforto em vídeo" value="Prefiro não aparecer"><span>Prefiro não aparecer</span></label>
              </div>
            </fieldset>
            <div class="field"><label for="gravacoes">Disponibilidade para gravações</label><select id="gravacoes" name="Disponibilidade para gravações"><option value="">Selecione</option><option>1x por mês</option><option>2x por mês</option><option>Semanal</option><option>Conforme necessidade</option><option>Não sei ainda</option></select></div>
            <div class="field"><label for="freq_conteudo">Frequência de conteúdo que considera possível</label><select id="freq_conteudo" name="Frequência de conteúdo"><option value="">Selecione</option><option>1–2 vezes por semana</option><option>3 vezes por semana</option><option>4–5 vezes por semana</option><option>Quero orientação</option></select></div>
            <fieldset class="field full">
              <legend>Formatos de interesse</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Reels"><span>Reels</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Vídeos educativos"><span>Vídeos educativos</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Stories"><span>Stories</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Posts estáticos"><span>Posts estáticos</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Carrosséis"><span>Carrosséis</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Artigos"><span>Artigos</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Lives"><span>Lives</span></label><label class="choice"><input type="checkbox" name="Formatos de conteúdo" value="Podcast / entrevistas"><span>Podcast / entrevistas</span></label>
              </div>
            </fieldset>
            <div class="field full"><label for="temas">Quais temas gostaria de abordar?</label><textarea id="temas" name="Temas de conteúdo"></textarea></div>
            <div class="field full"><label for="nao_abordar">Existe algum assunto que prefere não abordar?</label><textarea id="nao_abordar" name="Temas a evitar"></textarea></div>
            <fieldset class="field full">
              <legend>De onde você acredita que podem vir seus primeiros clientes?</legend>
              <div class="choices">
                <label class="choice"><input type="checkbox" name="Canais de aquisição" value="Indicação"><span>Indicação</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Networking"><span>Networking</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Instagram"><span>Instagram</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Google"><span>Google</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Empresas"><span>Empresas</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Eventos"><span>Eventos</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Parcerias"><span>Parcerias</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Conteúdo orgânico"><span>Conteúdo orgânico</span></label><label class="choice"><input type="checkbox" name="Canais de aquisição" value="Outros"><span>Outros</span></label>
              </div>
            </fieldset>
            <div class="field full"><label for="network">Rede de relacionamento que pode gerar oportunidades</label><textarea id="network" name="Rede de relacionamento" placeholder="Empresas, associações, grupos, parceiros, amigos, ex-colegas, contatos profissionais etc."></textarea></div>
          </div>
        </section>

        <!-- 7 -->
        <section class="step" data-step="7">
          <div class="step-header">
            <div class="step-num">07</div>
            <div><h2>Investimento e metas</h2><p>Essas respostas ajudam a propor uma estratégia realista e sustentável.</p></div>
          </div>
          <div class="grid">
            <div class="field">
              <label for="invest_mkt">Investimento mensal em gestão e marketing *</label>
              <select id="invest_mkt" name="Investimento mensal em marketing" required>
                <option value="">Selecione uma faixa</option>
                <option>Até R$ 1.000</option>
                <option>R$ 1.000 a R$ 1.500</option>
                <option>R$ 1.500 a R$ 2.500</option>
                <option>R$ 2.500 a R$ 4.000</option>
                <option>Acima de R$ 4.000</option>
                <option>Prefiro receber uma proposta antes</option>
              </select>
            </div>
            <div class="field">
              <label for="invest_ads">Investimento mensal em mídia / anúncios</label>
              <select id="invest_ads" name="Investimento mensal em mídia">
                <option value="">Selecione uma faixa</option>
                <option>Ainda não pretendo investir</option>
                <option>Até R$ 500</option>
                <option>R$ 500 a R$ 1.000</option>
                <option>R$ 1.000 a R$ 2.000</option>
                <option>R$ 2.000 a R$ 3.000</option>
                <option>Acima de R$ 3.000</option>
                <option>Ainda preciso definir</option>
              </select>
              <div class="hint">A estratégia será planejada considerando as normas aplicáveis à publicidade jurídica.</div>
            </div>
            <div class="field">
              <label for="invest_implantacao">Investimento inicial em estruturação da marca</label>
              <select id="invest_implantacao" name="Investimento inicial">
                <option value="">Selecione uma faixa</option>
                <option>Até R$ 1.000</option>
                <option>R$ 1.000 a R$ 2.500</option>
                <option>R$ 2.500 a R$ 5.000</option>
                <option>Acima de R$ 5.000</option>
                <option>Ainda não defini</option>
              </select>
            </div>
            <div class="field">
              <label for="prazo">Quando gostaria de iniciar?</label>
              <select id="prazo" name="Prazo de início"><option value="">Selecione</option><option>Imediatamente</option><option>Nos próximos 15 dias</option><option>Em até 30 dias</option><option>Em 2–3 meses</option><option>Ainda estou planejando</option></select>
            </div>
            <div class="field full"><label for="meta12">Onde você gostaria de estar profissionalmente daqui a 12 meses? *</label><textarea id="meta12" name="Meta de 12 meses" required></textarea></div>
            <div class="field"><label for="clientes_mes">Meta de novos clientes / mês</label><input id="clientes_mes" name="Meta de clientes por mês" type="text" placeholder="Ex.: 5, 10, 20..."></div>
            <div class="field"><label for="faturamento">Meta aproximada de faturamento mensal</label><input id="faturamento" name="Meta de faturamento" type="text" placeholder="Opcional"></div>
            <div class="field full"><label for="resultado_ideal">Qual seria o resultado ideal deste trabalho de marketing para você?</label><textarea id="resultado_ideal" name="Resultado ideal"></textarea></div>
          </div>
        </section>

        <!-- 8 -->
        <section class="step" data-step="8">
          <div class="step-header">
            <div class="step-num">08</div>
            <div><h2>Referências e conclusão</h2><p>Últimos pontos para entendermos concorrência, referências e expectativas.</p></div>
          </div>
          <div class="grid">
            <div class="field full"><label for="referencias">Advogados ou escritórios que considera referência</label><textarea id="referencias" name="Referências de mercado" placeholder="Cite até 3 e, se possível, informe Instagram ou site."></textarea></div>
            <div class="field full"><label for="concorrentes">Concorrentes diretos na sua cidade ou região</label><textarea id="concorrentes" name="Concorrentes diretos"></textarea></div>
            <div class="field full"><label for="diferencial">Como acredita que pode se diferenciar?</label><textarea id="diferencial" name="Diferenciais"></textarea></div>
            <div class="field full"><label for="expectativa">Qual a sua maior expectativa em relação à S3 Mídia? *</label><textarea id="expectativa" name="Expectativa com a S3 Mídia" required></textarea></div>
            <div class="field full"><label for="extra">Existe alguma informação importante que não perguntamos?</label><textarea id="extra" name="Informações adicionais"></textarea></div>
            <div class="field full">
              <div class="consent">
                <input type="checkbox" id="consentimento" name="Consentimento" value="Sim" required>
                <label for="consentimento">Autorizo a S3 Mídia a utilizar as informações deste briefing exclusivamente para diagnóstico, planejamento, criação de proposta e execução de serviços que venham a ser contratados.</label>
              </div>
              <div class="micro">Ao enviar, você confirma que as respostas representam suas preferências e objetivos atuais. Elas podem ser refinadas posteriormente em reunião estratégica.</div>
            </div>
          </div>
        </section>

        <div class="error<?= $formError !== '' ? ' show' : '' ?>" id="errorBox" role="alert"><?= e($formError !== '' ? $formError : 'Preencha os campos obrigatórios desta etapa antes de continuar.') ?></div>
        <div class="nav">
          <span class="save-note">Rascunho salvo automaticamente</span>
          <button class="btn-ghost" type="button" id="prevBtn" style="display:none">Voltar</button>
          <button class="btn-main" type="button" id="nextBtn">Continuar</button>
          <button class="btn-send" type="submit" id="submitBtn" style="display:none">Enviar briefing</button>
        </div>
      </form>
    </div>

    <footer>
      <strong>S3 Mídia — Marketing & Publicidade</strong><br>
      Briefing estratégico personalizado para <?= e($clientName) ?>.
    </footer>
  </div>

<script>
(() => {
  const form = document.getElementById('briefingForm');
  const steps = [...document.querySelectorAll('.step')];
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const submitBtn = document.getElementById('submitBtn');
  const bar = document.getElementById('progressBar');
  const stepLabel = document.getElementById('stepLabel');
  const percentLabel = document.getElementById('percentLabel');
  const errorBox = document.getElementById('errorBox');
  const serverErrorField = <?= json_encode(is_string($formErrorField) ? $formErrorField : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const storageKey = <?= json_encode($storageKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let current = 0;

  function showStep(index) {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((s,i) => s.classList.toggle('active', i === current));
    const pct = Math.round(((current + 1) / steps.length) * 100);
    bar.style.width = pct + '%';
    stepLabel.textContent = `Etapa ${current + 1} de ${steps.length}`;
    percentLabel.textContent = pct + '%';
    prevBtn.style.display = current === 0 ? 'none' : 'inline-block';
    nextBtn.style.display = current === steps.length - 1 ? 'none' : 'inline-block';
    submitBtn.style.display = current === steps.length - 1 ? 'inline-block' : 'none';
    errorBox.classList.remove('show');
    window.scrollTo({ top: document.querySelector('.progress-wrap').offsetTop - 10, behavior: 'smooth' });
  }

  function fieldTitle(el) {
    const label = el.id ? document.querySelector(`label[for="${CSS.escape(el.id)}"]`) : null;
    return label ? label.textContent.replace('*', '').trim() : 'Este campo';
  }

  function clearRequiredError(el) {
    el.classList.remove('required-error', 'server-error-focus');
    el.removeAttribute('aria-invalid');
    const hint = el.closest('.consent')?.querySelector('.required-hint') || el.parentElement?.querySelector('.required-hint');
    if (hint) hint.remove();
  }

  function markRequiredError(el) {
    const visualTarget = el.closest('.consent') || el;
    visualTarget.classList.add('required-error');
    el.setAttribute('aria-invalid', 'true');
    const container = visualTarget.closest('.field') || visualTarget.parentElement;
    if (container && !container.querySelector('.required-hint')) {
      const hint = document.createElement('span');
      hint.className = 'required-hint';
      hint.textContent = `${fieldTitle(el)} é obrigatório para continuar.`;
      container.appendChild(hint);
    }
  }

  function validateCurrent() {
    const required = [...steps[current].querySelectorAll('[required]')].filter(el => {
      const parent = el.closest('.conditional');
      return !parent || parent.classList.contains('show');
    });
    for (const el of required) {
      clearRequiredError(el);
      if (el.type === 'checkbox' && !el.checked) {
        markRequiredError(el);
        el.closest('.consent')?.scrollIntoView({behavior:'smooth', block:'center'});
        el.focus({preventScroll:true});
        errorBox.classList.add('show');
        errorBox.textContent = 'Complete o campo destacado para continuar.';
        return false;
      }
      if (!el.value.trim() || !el.checkValidity()) {
        markRequiredError(el);
        el.scrollIntoView({behavior:'smooth', block:'center'});
        el.focus({preventScroll:true});
        errorBox.classList.add('show');
        errorBox.textContent = 'Complete o campo destacado para continuar.';
        return false;
      }
    }
    return true;
  }

  nextBtn.addEventListener('click', () => {
    if (validateCurrent()) showStep(current + 1);
  });
  prevBtn.addEventListener('click', () => showStep(current - 1));

  function save() {
    const data = {};
    const fd = new FormData(form);
    for (const [k,v] of fd.entries()) {
      if (k === 'client_token' || k === 'csrf_token' || k === 'bot-field') continue;
      if (data[k]) data[k] = Array.isArray(data[k]) ? [...data[k], v] : [data[k], v];
      else data[k] = v;
    }
    localStorage.setItem(storageKey, JSON.stringify(data));
  }

  function restore() {
    try {
      const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
      for (const [name,val] of Object.entries(data)) {
        const els = [...form.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
        els.forEach(el => {
          if (el.type === 'checkbox' || el.type === 'radio') {
            const vals = Array.isArray(val) ? val : [val];
            el.checked = vals.includes(el.value);
          } else {
            el.value = Array.isArray(val) ? val[0] : val;
          }
        });
      }
    } catch(e) {}
  }

  form.addEventListener('input', (event) => { clearRequiredError(event.target); save(); });
  form.addEventListener('change', (event) => { clearRequiredError(event.target); save(); updateConditionals(); });

  function updateConditionals() {
    const siteSel = document.getElementById('tem_site');
    const siteField = document.getElementById('site_url');
    siteField.classList.toggle('show', siteSel.value === 'Sim');
  }

  restore();
  updateConditionals();

  form.addEventListener('submit', (e) => {
    if (!validateCurrent()) {
      e.preventDefault();
      return;
    }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';

  });

  showStep(0);
  if (serverErrorField) {
    const target = [...form.querySelectorAll('[name]')].find(el => el.name === serverErrorField);
    const targetStep = target ? steps.findIndex(step => step.contains(target)) : -1;
    if (targetStep >= 0) showStep(targetStep);
    errorBox.classList.add('show');
    errorBox.textContent = 'Complete o campo destacado para continuar.';
    if (target) {
      markRequiredError(target);
      window.setTimeout(() => target.focus({preventScroll:true}), 80);
    }
  }
})();
</script>
</body>
</html>

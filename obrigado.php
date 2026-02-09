<?php
$makeWebhook = 'https://hook.eu2.make.com/rqwaw3g4ldz8vpxp3rl669q9fklv5534';

function clean_field(string $key): string {
  return trim((string)($_POST[$key] ?? ''));
}

$makeStatus = $_GET['status'] ?? '';
$makeError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $payload = [
    'lang' => 'pt',
    'firstName' => clean_field('firstName'),
    'lastName' => clean_field('lastName'),
    'email' => clean_field('email'),
    'phone' => clean_field('phone'),
    'setor' => clean_field('setor'),
    'service' => clean_field('service'),
    'message' => clean_field('message'),
    'sourceUrl' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
    'submittedAt' => gmdate('c'),
    'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
  ];

  $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  if ($jsonPayload !== false) {
    $httpCode = 0;

    if (function_exists('curl_init')) {
      $ch = curl_init($makeWebhook);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 12,
      ]);
      curl_exec($ch);
      $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlErr = curl_error($ch);
      curl_close($ch);

      if ($curlErr) {
        $makeError = $curlErr;
      }
    } else {
      $context = stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' => "Content-Type: application/json\r\n",
          'content' => $jsonPayload,
          'timeout' => 12,
        ],
      ]);
      @file_get_contents($makeWebhook, false, $context);
      if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
      }
    }

    $makeStatus = ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'error';
  } else {
    $makeStatus = 'error';
    $makeError = 'Failed to encode payload.';
  }

  $target = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
  $location = $target . '?status=' . urlencode($makeStatus);
  if ($makeStatus === 'error' && $makeError !== '') {
    $location .= '&reason=' . urlencode(substr($makeError, 0, 120));
  }
  header('Location: ' . $location, true, 303);
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="Obrigado pelo contacto — DS Clima Service. Resposta rápida e acompanhamento real." />
  <title>Obrigado pelo contacto | DS Clima Service</title>

  <script src="https://cdn.tailwindcss.com"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#013365",
            secondary: "#4a94b8",
            accent: "#3ec2f4",
          },
        },
      },
    };
  </script>

  <style>
    html { font-family: "Inter", system-ui, sans-serif; }
  </style>
</head>

<body class="font-sans antialiased bg-white">

  <!-- Header (igual ao da landing) -->
  <header id="site-header"
    class="fixed top-0 z-50 w-full transition-all duration-300 bg-primary">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

      <a href="/" class="flex items-center">
        <img
          src="logos/Logo_Horizontal_ComDescriptor_Inverso_Editado.svg"
          alt="DS Clima Service"
          class="h-7 md:h-8 w-auto"
        />
      </a>

      <a
        href="https://wa.me/351961653736?text=Olá,%20enviei%20um%20pedido%20pelo%20site%20e%20queria%20acelerar%20o%20contacto."
        target="_blank"
        rel="noopener"
        class="inline-flex items-center bg-accent hover:bg-secondary text-white font-bold px-5 py-3 rounded-lg shadow transition"
      >
        LIGAR
      </a>
    </div>
  </header>

  <!-- Hero / Obrigado (visual coerente com a landing) -->
  <section class="relative min-h-screen overflow-hidden pt-20">

    <!-- Background (reaproveita o mesmo asset do hero para coerência) -->
    <img
      src="logos/ds_carrinha_bg.webp"
      alt=""
      class="absolute inset-0 h-full w-full object-cover"
    />
    <div class="absolute inset-0 bg-black/55"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/30 to-black/70"></div>

    <div class="relative z-10 mx-auto max-w-6xl px-5 py-16">
      <div class="max-w-xl">

        <!-- Badge -->
        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-white/90 text-sm">
          <span class="inline-block h-2 w-2 rounded-full bg-accent"></span>
          <?php if ($makeStatus === "error"): ?>Pedido recebido (com alerta no envio interno)<?php else: ?>Pedido recebido com sucesso<?php endif; ?>
        </div>

        <h1 class="mt-6 text-3xl md:text-5xl font-extrabold leading-tight text-white">
          Obrigado pelo contacto.
        </h1>

        <p class="mt-5 text-base md:text-lg text-white/85 leading-relaxed">
          A sua mensagem foi recebida.
          Vamos analisar o pedido e responder-lhe com a solução mais adequada,
          com a mesma lógica de trabalho da DS:
          <span class="font-semibold text-white">clareza, execução e acompanhamento</span>.
        </p>

        <!-- Card com expectativa (sem promessas fracas) -->
        <div class="mt-8 rounded-2xl bg-white/95 p-6 shadow-xl backdrop-blur">
          <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">
            O que acontece agora
          </p>

          <ul class="mt-4 space-y-3 text-gray-800 text-sm leading-relaxed">
            <li class="flex gap-3">
              <span class="mt-1 inline-block h-2 w-2 rounded-full bg-accent"></span>
              Confirmamos os detalhes do pedido (tipo de serviço + local + urgência).
            </li>
            <li class="flex gap-3">
              <span class="mt-1 inline-block h-2 w-2 rounded-full bg-accent"></span>
              Se for assistência técnica, priorizamos diagnóstico e resolução responsável.
            </li>
            <li class="flex gap-3">
              <span class="mt-1 inline-block h-2 w-2 rounded-full bg-accent"></span>
              Se for instalação, validamos dimensionamento e condições do espaço antes de orçamentar.
            </li>
          </ul>

          <!-- CTA estilo “pill” como na landing -->
          <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a
              href="https://wa.me/351961653736?text=Olá,%20enviei%20um%20pedido%20pelo%20formulário.%20Podem%20ajudar-me%20a%20agendar%20o%20diagnóstico%20técnico?"
              target="_blank"
              rel="noopener"
              class="relative inline-flex items-center justify-center
                     rounded-full bg-primary
                     px-6 py-4
                     text-[15px] font-bold
                     text-white shadow-lg
                     hover:bg-secondary transition"
            >
              FALAR NO WHATSAPP
              <span
                class="absolute right-2 top-1/2 -translate-y-1/2
                       grid h-10 w-10 place-items-center
                       rounded-full bg-accent text-white"
                aria-hidden="true"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 17L17 7M17 7H9m8 0v8" />
                </svg>
              </span>
            </a>

            <a
              href="/#servicos"
              class="inline-flex items-center justify-center
                     rounded-full bg-white/90
                     px-6 py-4
                     text-[15px] font-bold
                     text-primary shadow-lg
                     hover:bg-white transition"
            >
              VOLTAR À PÁGINA
            </a>
          </div>

          <p class="mt-4 text-xs text-gray-500">
            Não é necessário reenviar o formulário.
          </p>
        </div>
      </div>
    </div>

        </div>
  </section>

  <!-- Footer (igual ao da landing) -->
  <footer class="bg-primary text-white py-12">
    <div class="max-w-6xl mx-auto px-6">
      <div class="grid gap-8 md:grid-cols-3">

        <div>
          <h3 class="font-bold text-lg mb-4">DS Clima Service</h3>
          <p class="text-sm text-gray-300">
            Instalação, manutenção e assistência técnica em Lagos, Portimão, Lagoa, Burgau e Sagres.
          </p>
        </div>

        <div>
          <h3 class="font-bold text-lg mb-4">Contacto</h3>
          <ul class="text-sm space-y-2">
            <li>
              <a href="tel:+351961653736" class="text-gray-300 hover:text-accent transition">
                +351 961 653 736
              </a>
            </li>
            <li>
              <a href="mailto:geral@dsclima.pt" class="text-gray-300 hover:text-accent transition">
                geral@dsclima.pt
              </a>
            </li>
            <li class="text-gray-300">
              R. José Afonso 7 Loja 4, 8600-592 Lagos
            </li>
          </ul>
        </div>

        <div>
          <h3 class="font-bold text-lg mb-4 mt-4">Legal</h3>
          <ul class="text-sm space-y-2">
            <li><a href="#" class="text-gray-300 hover:text-accent transition">Política de Privacidade</a></li>
            <li><a href="#" class="text-gray-300 hover:text-accent transition">Termos e Condições</a></li>
          </ul>
        </div>
      </div>

      <div class="mt-8 border-t border-gray-700 pt-8 text-center text-sm text-gray-400">
        <p>&copy; 2026 DS Clima Service. Todos os direitos reservados.</p>
      </div>
    </div>
  </footer>

</body>
</html>

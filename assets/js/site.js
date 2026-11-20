/*
 * site.js - Comportamento visual do layout (vale para todas as páginas).
 *
 *   1. Menu do celular (abre/fecha o painel)
 *   2. Submenu "Cardápio"
 *   3. Revelar os blocos conforme a página rola
 *   4. Carrossel de fotos do topo da página inicial
 *
 * Carregado com "defer": roda depois que o HTML já foi lido.
 */
(function () {
    'use strict';

    // Avisa o script do <head> que este arquivo carregou. Se não carregar, o
    // <head> tira a classe "js" e nada fica invisível esperando animação.
    window.__siteJs = true;
    document.documentElement.classList.add('js');

    var semMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // -----------------------------------------------------------------
    // 1. Menu do celular
    // -----------------------------------------------------------------
    var cabecalho = document.getElementById('siteHeader');
    var botaoMenu = cabecalho ? cabecalho.querySelector('.nav-toggle') : null;

    function definirMenu(aberto) {
        cabecalho.classList.toggle('is-open', aberto);
        botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        // Com o menu aberto a página de trás não rola
        document.body.classList.toggle('sem-rolagem', aberto);

        // O painel cobre a tela inteira, então o resto da página fica "inerte":
        // o Tab e o leitor de tela não entram lá (senão o foco sumiria atrás do
        // painel). Ficam de fora o próprio menu, os <script> e o aviso para o
        // leitor de tela (aria-live), que precisa continuar funcionando.
        Array.prototype.forEach.call(document.body.children, function (elemento) {
            if (elemento !== cabecalho && elemento.tagName !== 'SCRIPT' && !elemento.hasAttribute('aria-live')) {
                elemento.inert = aberto;
            }
        });
        // O logo da barra também fica atrás do painel aberto
        var logo = cabecalho.querySelector('.site-logo');
        if (logo) { logo.inert = aberto; }
    }

    if (botaoMenu) {
        botaoMenu.addEventListener('click', function () {
            definirMenu(!cabecalho.classList.contains('is-open'));
        });

        // Esc fecha o menu e devolve o foco para o botão
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && cabecalho.classList.contains('is-open')) {
                definirMenu(false);
                botaoMenu.focus();
            }
        });

        // Girou o celular ou aumentou a janela: o painel some, então destrava a rolagem
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && cabecalho.classList.contains('is-open')) {
                definirMenu(false);
            }
        });
    }

    // -----------------------------------------------------------------
    // 2. Submenu
    // -----------------------------------------------------------------
    var alternadores = document.querySelectorAll('.site-nav__alternar');
    Array.prototype.forEach.call(alternadores, function (botao) {
        botao.addEventListener('click', function () {
            var grupo = botao.parentNode;
            var aberto = grupo.classList.toggle('is-open');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    });

    // -----------------------------------------------------------------
    // 3. Revelar ao rolar
    // O CSS deixa .revealable invisível; aqui a classe .revealed mostra o
    // bloco quando ele entra na tela.
    // -----------------------------------------------------------------
    var blocos = document.querySelectorAll('.revealable');
    if (blocos.length) {
        if ('IntersectionObserver' in window && !semMovimento) {
            var observador = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada) {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.add('revealed');
                        observador.unobserve(entrada.target); // só anima uma vez
                    }
                });
            }, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });

            Array.prototype.forEach.call(blocos, function (bloco) {
                observador.observe(bloco);
            });
        } else {
            // Navegador antigo ou "menos movimento": mostra tudo de uma vez
            Array.prototype.forEach.call(blocos, function (bloco) {
                bloco.classList.add('revealed');
            });
        }
    }

    // -----------------------------------------------------------------
    // 4. Carrossel do topo
    // -----------------------------------------------------------------
    var hero = document.querySelector('.hero--galeria');
    if (hero) {
        var slides = hero.querySelectorAll('.hero__slide');
        var botaoPausa = hero.querySelector('.hero__pausa');
        var atual = 0;
        var relogio = null;
        var TEMPO_ENTRE_FOTOS = 5000; // 3 s parada + 2 s de transição (o fade está no CSS)

        // As fotos seguintes só são baixadas quando forem aparecer (data-bg)
        function carregar(slide) {
            var endereco = slide.getAttribute('data-bg');
            if (endereco) {
                slide.style.backgroundImage = "url('" + endereco + "')";
                slide.removeAttribute('data-bg');
            }
        }

        // Só a foto da vez é "vista" pelo leitor de tela; as outras ficam escondidas
        // dele (aria-hidden), senão ele leria as 4 descrições sem saber qual aparece
        function mostrar(indice) {
            slides[atual].classList.remove('is-active');
            slides[atual].setAttribute('aria-hidden', 'true');
            atual = (indice + slides.length) % slides.length;
            carregar(slides[atual]);
            slides[atual].classList.add('is-active');
            slides[atual].removeAttribute('aria-hidden');
            carregar(slides[(atual + 1) % slides.length]); // adianta a próxima
        }

        function tocar() {
            parar();
            relogio = window.setInterval(function () { mostrar(atual + 1); }, TEMPO_ENTRE_FOTOS);
        }

        function parar() {
            if (relogio) {
                window.clearInterval(relogio);
                relogio = null;
            }
        }

        // Botão de alternar: o nome fica fixo ("Pausar a troca de fotos") e o
        // aria-pressed diz se está pausado. Trocar o nome E o estado ao mesmo
        // tempo confundia o leitor de tela ("Voltar a trocar..., pressionado").
        function atualizarBotaoPausa(pausado) {
            if (!botaoPausa) { return; }
            botaoPausa.setAttribute('aria-pressed', pausado ? 'true' : 'false');
            var icone = botaoPausa.querySelector('i');
            if (icone) { icone.className = pausado ? 'fas fa-play' : 'fas fa-pause'; }
        }

        if (slides.length > 1) {
            var anterior = hero.querySelector('.hero__seta--anterior');
            var proxima = hero.querySelector('.hero__seta--proxima');
            var pausado = semMovimento;

            if (anterior) {
                anterior.addEventListener('click', function () {
                    mostrar(atual - 1);
                    if (!pausado) { tocar(); } // recomeça a contagem
                });
            }
            if (proxima) {
                proxima.addEventListener('click', function () {
                    mostrar(atual + 1);
                    if (!pausado) { tocar(); }
                });
            }
            if (botaoPausa) {
                botaoPausa.addEventListener('click', function () {
                    pausado = !pausado;
                    if (pausado) { parar(); } else { tocar(); }
                    atualizarBotaoPausa(pausado);
                });
            }

            atualizarBotaoPausa(pausado);
            Array.prototype.forEach.call(slides, function (slide, posicao) {
                if (posicao !== atual) { slide.setAttribute('aria-hidden', 'true'); }
            });
            carregar(slides[1]);
            if (!pausado) { tocar(); }

            // Aba escondida não precisa ficar trocando foto
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { parar(); } else if (!pausado) { tocar(); }
            });
        }
    }
})();

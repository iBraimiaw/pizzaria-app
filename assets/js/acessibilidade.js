/*
 * acessibilidade.js - Pequenas ajudas de acessibilidade (todas as páginas).
 *
 *   1. announce(): fala uma mensagem para o leitor de tela
 *   2. Enter e Espaço "clicam" em elementos com role="button"
 *   3. Janela (modal) aberta: Esc fecha e o Tab dá a volta dentro dela
 */
(function () {
    'use strict';

    // -----------------------------------------------------------------
    // 1. Anúncio para leitores de tela
    // Precisa existir na página um <div id="liveStatus" aria-live="polite">.
    // Só cria a função se a página ainda não tiver a dela.
    // -----------------------------------------------------------------
    if (typeof window.announce !== 'function') {
        window.announce = function (mensagem) {
            var regiao = document.getElementById('liveStatus');
            if (!regiao) { return; }
            regiao.textContent = mensagem;
            window.setTimeout(function () { regiao.textContent = ''; }, 3000);
        };
    }

    // -----------------------------------------------------------------
    // 2. Teclado em "botões" que não são <button>
    // Um <div role="button"> recebe foco com Tab, mas o navegador não clica
    // nele sozinho com Enter/Espaço. Aqui a gente faz isso.
    // (Se o elemento já trata a tecla por conta própria, não faz nada.)
    // -----------------------------------------------------------------
    document.addEventListener('keydown', function (evento) {
        var alvo = evento.target;
        if (!alvo || !alvo.getAttribute || alvo.getAttribute('role') !== 'button') { return; }
        if (alvo.tagName === 'BUTTON' || alvo.tagName === 'A') { return; }
        if (alvo.hasAttribute('onkeypress') || alvo.hasAttribute('onkeydown')) { return; }

        if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault(); // Espaço não rola a página
            // Tecla segurada repete o evento: um clique só (um <button> de verdade
            // também não clica várias vezes)
            if (evento.repeat) { return; }
            alvo.click();
        }
    });

    // -----------------------------------------------------------------
    // 3. Janela (modal) aberta
    // Esc "clica" no X (assim a própria página fecha do jeito dela e devolve o
    // foco) e o Tab dá a volta dentro da janela em vez de sair para a página
    // de trás. Se a página já tratou o Esc antes, a janela já está fechada
    // aqui e nada acontece.
    // -----------------------------------------------------------------
    function janelaAberta() {
        var modais = document.querySelectorAll('.modal');
        for (var i = 0; i < modais.length; i++) {
            if (modais[i].style.display && modais[i].style.display !== 'none') { return modais[i]; }
        }
        return null;
    }

    document.addEventListener('keydown', function (evento) {
        var modal = janelaAberta();
        if (!modal) { return; }

        if (evento.key === 'Escape') {
            var fechar = modal.querySelector('.close');
            if (fechar) { fechar.click(); } else { modal.style.display = 'none'; }
            return;
        }

        if (evento.key !== 'Tab') { return; }
        var focaveis = modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
        if (!focaveis.length) { return; }
        var primeiro = focaveis[0];
        var ultimo = focaveis[focaveis.length - 1];
        if (evento.shiftKey && document.activeElement === primeiro) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primeiro.focus();
        }
    });
})();

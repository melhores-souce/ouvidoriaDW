/**
 * auth.js — Autenticação: Login e Cadastro
 * Ouvidoria Municipal — Ceará
 *
 * Responsabilidades:
 *   - Validação de formulários em tempo real
 *   - AJAX para login (POST /api/login.php)
 *   - AJAX para cadastro (POST /api/cadastro.php)
 *   - Redirecionamentos pós-ação
 *   - Força de senha + hints
 *   - Modal de recuperação de senha
 */

const Auth = (() => {

  /* ═══════════════════════════════════════════════════
     MÓDULO LOGIN
  ═══════════════════════════════════════════════════ */
  function initLogin() {
    _bindPasswordToggle('#toggleSenha', '#loginSenha', '#eyeIcon');
    _bindLoginInlineValidation();
    _bindLoginSubmit();
    _bindForgotModal();
    _autofillRemembered();
  }

  /* ── Lembrar e-mail ──────────────────────────────── */
  function _autofillRemembered() {
    const saved = localStorage.getItem('ouvid_remember_email');
    if (saved) {
      $('#loginEmail').val(saved);
      $('#rememberMe').prop('checked', true);
    }
  }

  /* ── Validação inline (ao sair do campo) ──────────── */
  function _bindLoginInlineValidation() {
    $('#loginEmail').on('blur', function () {
      const v = $.trim($(this).val());
      if (v && !_isValidEmail(v)) {
        _fieldError(this, '#feedbackEmail', 'E-mail inválido.');
      } else if (v) {
        _fieldValid(this, '#feedbackEmail', '');
      } else {
        _fieldReset(this, '#feedbackEmail');
      }
    });

    $('#loginSenha').on('blur', function () {
      const v = $(this).val();
      if (v && v.length < 6) {
        _fieldError(this, '#feedbackSenha', 'Senha muito curta.');
      } else if (v) {
        _fieldValid(this, '#feedbackSenha', '');
      } else {
        _fieldReset(this, '#feedbackSenha');
      }
    });

    // Enter key
    $('#loginForm input').on('keydown', function (e) {
      if (e.key === 'Enter') $('#loginForm').trigger('submit');
    });
  }

  /* ── Submit login ────────────────────────────────── */
  function _bindLoginSubmit() {
    $('#loginForm').on('submit', function (e) {
      e.preventDefault();
      _doLogin();
    });
  }

  function _doLogin() {
    const email = $.trim($('#loginEmail').val());
    const senha = $('#loginSenha').val();
    let valid = true;

    // Validação client-side
    if (!email) { _fieldError('#loginEmail', '#feedbackEmail', 'O e-mail é obrigatório.'); valid = false; }
    else if (!_isValidEmail(email)) { _fieldError('#loginEmail', '#feedbackEmail', 'E-mail inválido.'); valid = false; }
    else { _fieldValid('#loginEmail', '#feedbackEmail', ''); }

    if (!senha) { _fieldError('#loginSenha', '#feedbackSenha', 'A senha é obrigatória.'); valid = false; }
    else { _fieldValid('#loginSenha', '#feedbackSenha', ''); }

    if (!valid) return;

    _setLoginLoading(true);
    _hideAlert('#authAlert');

    const remember = $('#rememberMe').is(':checked');

    $.ajax({
      method:      'POST',
      url:         'api/login.php',
      contentType: 'application/json',
      data:        JSON.stringify({ email, senha }),
      timeout:     15000,
    })
    .done(res => {
      if (res.success) {
        if (remember) {
          localStorage.setItem('ouvid_remember_email', email);
        } else {
          localStorage.removeItem('ouvid_remember_email');
        }
        _showAlert('#authAlert', 'success',
          '<i class="fa-solid fa-circle-check me-2"></i>Login realizado! Redirecionando...');
        setTimeout(() => { window.location.href = 'index.html#manifestacao'; }, 1200);
      } else {
        // success=false mas status 200 = credenciais erradas
        _showAlert('#authAlert', 'danger',
          `<i class="fa-solid fa-circle-xmark me-2"></i>${res.message || 'E-mail ou senha incorretos.'}`);
        _fieldError('#loginSenha', '#feedbackSenha', '');
        $('#loginSenha').val('').focus();
      }
    })
    .fail(xhr => {
      const msg = _parseError(xhr);
      if (xhr.status === 404) {
        // Usuário não encontrado → redireciona ao cadastro
        _showAlert('#authAlert', 'warning',
          '<i class="fa-solid fa-triangle-exclamation me-2"></i>Usuário não encontrado. Redirecionando para o cadastro...');
        setTimeout(() => {
          window.location.href = `cadastro.html?email=${encodeURIComponent(email)}`;
        }, 2000);
      } else {
        _showAlert('#authAlert', 'danger',
          `<i class="fa-solid fa-circle-xmark me-2"></i>${msg}`);
      }
    })
    .always(() => _setLoginLoading(false));
  }

  function _setLoginLoading(on) {
    $('#loginBtn').prop('disabled', on);
    $('#loginText').toggleClass('d-none', on);
    $('#loginSpinner').toggleClass('d-none', !on);
  }

  /* ── Forgot Password Modal ───────────────────────── */
  function _bindForgotModal() {
    $('#forgotLink').on('click', function (e) {
      e.preventDefault();
      const modal = new bootstrap.Modal('#forgotModal');
      modal.show();
    });

    $('#btnForgotSend').on('click', function () {
      const email = $.trim($('#forgotEmail').val());
      if (!_isValidEmail(email)) {
        Utils.showToast('Informe um e-mail válido.', 'warning');
        return;
      }
      const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
      $.ajax({
        method: 'POST',
        url: 'api/forgot.php',
        contentType: 'application/json',
        data: JSON.stringify({ email }),
        timeout: 10000,
      })
      .always(() => {
        // Sempre mostra sucesso (segurança: não revelar se e-mail existe)
        bootstrap.Modal.getInstance('#forgotModal')?.hide();
        Utils.showToast('Se o e-mail existir, você receberá as instruções em instantes.', 'info', 'E-mail enviado');
        $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-2"></i>Enviar link');
        $('#forgotEmail').val('');
      });
    });
  }

  /* ═══════════════════════════════════════════════════
     MÓDULO CADASTRO
  ═══════════════════════════════════════════════════ */
  let cadStep = 1;

  function initCadastro() {
    _bindPasswordToggle('#toggleCadSenha',  '#cadSenha',       '#cadEyeIcon1');
    _bindPasswordToggle('#toggleCadConfirm','#cadConfirmSenha','#cadEyeIcon2');
    _bindStrengthMeter();
    _bindCadStepNavigation();
    _bindCadInlineValidation();
    _prefillEmailFromURL();
  }

  /* Pré-preencher e-mail se vier da query string */
  function _prefillEmailFromURL() {
    const params = new URLSearchParams(window.location.search);
    const email  = params.get('email');
    if (email) $('#cadEmail').val(decodeURIComponent(email));
  }

  /* ── Navegação de steps ──────────────────────────── */
  function _bindCadStepNavigation() {
    $('#cad-next-2').on('click', () => { if (_validateCadStep1()) _goToCadStep(2); });
    $('#cad-next-3').on('click', () => { if (_validateCadStep2()) { _buildCadReview(); _goToCadStep(3); } });
    $('#cad-back-1').on('click', () => _goToCadStep(1));
    $('#cad-back-2').on('click', () => _goToCadStep(2));

    $('#cadastroForm').on('submit', function (e) {
      e.preventDefault();
      _doRegister();
    });
  }

  function _goToCadStep(step) {
    $(`#cad-step-${cadStep}`).removeClass('active');
    $(`#cstep-${cadStep}`).removeClass('active').toggleClass('done', cadStep < step);
    cadStep = step;
    $(`#cad-step-${cadStep}`).addClass('active');
    $(`#cstep-${cadStep}`).addClass('active');
    // Lines
    for (let i = 1; i <= 3; i++) {
      $(`#cstep-${i}`).next('.cad-step-line').toggleClass('done', i < step);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* ── Validação step 1 ────────────────────────────── */
  // ATUALIZADO: campos reais de tbusuarios (nome, email, serie, curso)
  // Removidos: cpf, nascimento, municipio (não existem na tabela)
  function _validateCadStep1() {
    let ok = true;

    const nome  = $.trim($('#cadNome').val());
    const email = $.trim($('#cadEmail').val());
    const serie = $('#cadSerie').val();
    const curso = $('#cadCurso').val();

    if (nome.length < 3) {
      _fieldError('#cadNome', '#fb-cadNome', 'Informe seu nome completo.');
      ok = false;
    } else { _fieldValid('#cadNome', '#fb-cadNome', ''); }

    if (!_isValidEmail(email)) {
      _fieldError('#cadEmail', '#fb-cadEmail', 'E-mail inválido.');
      ok = false;
    } else { _fieldValid('#cadEmail', '#fb-cadEmail', ''); }

    if (!serie) {
      _fieldError('#cadSerie', '#fb-cadSerie', 'Selecione a série.');
      ok = false;
    } else { _fieldValid('#cadSerie', '#fb-cadSerie', ''); }

    if (!curso) {
      _fieldError('#cadCurso', '#fb-cadCurso', 'Selecione o curso.');
      ok = false;
    } else { _fieldValid('#cadCurso', '#fb-cadCurso', ''); }

    if (!ok) Utils.showToast('Preencha os campos obrigatórios corretamente.', 'error');
    return ok;
  }

  /* ── Validação step 2 (senha) ────────────────────── */
  function _validateCadStep2() {
    let ok = true;
    const senha   = $('#cadSenha').val();
    const confirm = $('#cadConfirmSenha').val();

    const score = _calcStrength(senha);
    if (score < 2) { _fieldError('#cadSenha','#fb-cadSenha','A senha não atende aos requisitos mínimos.'); ok=false; }
    else { _fieldValid('#cadSenha','#fb-cadSenha',''); }

    if (senha !== confirm) { _fieldError('#cadConfirmSenha','#fb-cadConfirm','As senhas não coincidem.'); ok=false; }
    else if (confirm) { _fieldValid('#cadConfirmSenha','#fb-cadConfirm','Senhas iguais!'); }

    if (!ok) Utils.showToast('Corrija os problemas com a senha.', 'error');
    return ok;
  }

  /* ── Validação inline cadastro ───────────────────── */
  // ATUALIZADO: removidas máscaras de cpf e telefone (campos removidos)
  // Adicionadas validações inline de serie e curso
  function _bindCadInlineValidation() {
    $('#cadEmail').on('blur', function () {
      const v = $.trim($(this).val());
      if (v && !_isValidEmail(v)) _fieldError(this, '#fb-cadEmail', 'E-mail inválido.');
      else if (v) _fieldValid(this, '#fb-cadEmail', '');
    });
    $('#cadSerie').on('change', function () {
      if ($(this).val()) _fieldValid(this, '#fb-cadSerie', '');
    });
    $('#cadCurso').on('change', function () {
      if ($(this).val()) _fieldValid(this, '#fb-cadCurso', '');
    });
    $('#cadSenha').on('input', _updateStrengthUI);
    $('#cadConfirmSenha').on('input', function () {
      const match = $(this).val() === $('#cadSenha').val();
      if ($(this).val()) {
        if (match) _fieldValid(this, '#fb-cadConfirm', 'Senhas iguais!');
        else _fieldError(this, '#fb-cadConfirm', 'As senhas não coincidem.');
      }
    });
  }

  /* ── Strength Meter ──────────────────────────────── */
  function _bindStrengthMeter() {
    $('#cadSenha').on('input', _updateStrengthUI);
  }

  function _updateStrengthUI() {
    const pwd = $('#cadSenha').val();
    const score = _calcStrength(pwd);
    const labels = ['—', 'Fraca', 'Regular', 'Boa', 'Forte'];
    const classes = ['', 'weak', 'fair', 'good', 'strong'];

    for (let i = 1; i <= 4; i++) {
      $(`#sbar${i}`).attr('class', 'sbar' + (i <= score ? ` ${classes[score]}` : ''));
    }
    $('#strengthLabel').text(labels[score]).css('color', [
      '',
      'var(--ce-danger)',
      '#c07a00',
      'var(--ce-gold-deep)',
      'var(--ce-green)',
    ][score]);

    // Hints — mínimo 6 alinhado com cadastro.php (mb_strlen >= 6)
    const hints = [
      { id: '#hint-len',   ok: pwd.length >= 6 },
      { id: '#hint-upper', ok: /[A-Z]/.test(pwd) },
      { id: '#hint-num',   ok: /\d/.test(pwd) },
      { id: '#hint-sym',   ok: /[^A-Za-z0-9]/.test(pwd) },
    ];
    hints.forEach(h => $(h.id).toggleClass('ok', h.ok));
  }

  function _calcStrength(pwd) {
    if (!pwd) return 0;
    let s = 0;
    if (pwd.length >= 6)           s++; // mínimo 6 (alinhado com backend)
    if (/[A-Z]/.test(pwd))         s++;
    if (/\d/.test(pwd))            s++;
    if (/[^A-Za-z0-9]/.test(pwd)) s++;
    return s;
  }

  /* ── Build Review ────────────────────────────────── */
  // ATUALIZADO: campos reais de tbusuarios
  function _buildCadReview() {
    const serieTexto = { '1': '1º ano', '2': '2º ano', '3': '3º ano' };
    const fields = [
      { label: 'Nome',       value: $('#cadNome').val() },
      { label: 'E-mail',     value: $('#cadEmail').val() },
      { label: 'Matrícula',  value: $('#cadMatricula').val() || '—' },
      { label: 'Série',      value: serieTexto[$('#cadSerie').val()] || '—' },
      { label: 'Curso',      value: $('#cadCurso option:selected').text() || '—' },
    ];
    const html = fields.map(f => `
      <div class="cad-rv-item">
        <span class="cad-rv-label">${f.label}</span>
        <span class="cad-rv-value">${_sanitize(f.value)}</span>
      </div>`).join('');
    $('#cadReviewContent').html(html);
  }

  /* ── Registrar ───────────────────────────────────── */
  // ATUALIZADO: payload com campos reais de tbusuarios
  // ATUALIZADO: sucesso redireciona direto (ativo=1, sem e-mail de ativação)
  function _doRegister() {
    if (!$('#termosCad').is(':checked') || !$('#lgpdCad').is(':checked')) {
      Utils.showToast('Você precisa aceitar os Termos de Uso e a LGPD para continuar.', 'warning');
      return;
    }

    // Payload alinhado com as colunas reais de tbusuarios
    const payload = {
      nome:      $.trim($('#cadNome').val()),
      email:     $.trim($('#cadEmail').val()),
      matricula: $.trim($('#cadMatricula').val()),
      serie:     parseInt($('#cadSerie').val()) || 0,
      curso:     $('#cadCurso').val(),
      senha:     $('#cadSenha').val(),
    };

    _setCadLoading(true);
    _hideAlert('#cadAlert');

    $.ajax({
      method:      'POST',
      url:         'api/cadastro.php',
      contentType: 'application/json',
      data:        JSON.stringify(payload),
      timeout:     15000,
    })
    .done(res => {
      if (res.success) {
        // Mostrar step de sucesso com o nome do aluno
        $('#successNome').text('Bem-vindo(a), ' + _sanitize(payload.nome) + '!');
        $(`#cad-step-${cadStep}`).removeClass('active');
        $(`#cstep-${cadStep}`).removeClass('active').addClass('done');
        $('#cad-step-success').addClass('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Redirecionar automaticamente após 3 segundos
        // (conta já ativa — sem necessidade de verificar e-mail)
        let count = 3;
        const timer = setInterval(() => {
          count--;
          $('#countdownNum').text(count);
          if (count <= 0) {
            clearInterval(timer);
            window.location.href = res.redirect || 'index.html#manifestacao';
          }
        }, 1000);

      } else {
        _showAlert('#cadAlert', 'danger',
          `<i class="fa-solid fa-circle-xmark me-2"></i>${res.message || 'Erro ao criar conta. Tente novamente.'}`);
      }
    })
    .fail(xhr => {
      const msg = _parseError(xhr);
      if (xhr.status === 409) {
        _showAlert('#cadAlert', 'warning',
          '<i class="fa-solid fa-triangle-exclamation me-2"></i>Este e-mail já está cadastrado. ' +
          '<a href="login.html" class="auth-link-sm ms-1">Fazer login</a>');
      } else {
        _showAlert('#cadAlert', 'danger',
          `<i class="fa-solid fa-circle-xmark me-2"></i>${msg}`);
      }
    })
    .always(() => _setCadLoading(false));
  }

  function _setCadLoading(on) {
    $('#cadSubmitBtn').prop('disabled', on);
    $('#cadSubmitText').toggleClass('d-none', on);
    $('#cadSubmitSpinner').toggleClass('d-none', !on);
  }

  /* ═══════════════════════════════════════════════════
     UTILITÁRIOS PRIVADOS
  ═══════════════════════════════════════════════════ */

  /* Toggle visibilidade de senha */
  function _bindPasswordToggle(toggleId, inputId, iconId) {
    $(toggleId).on('click', function () {
      const $input = $(inputId);
      const type   = $input.attr('type') === 'password' ? 'text' : 'password';
      $input.attr('type', type);
      $(iconId).toggleClass('fa-eye fa-eye-slash');
    });
  }

  /* Validações */
  function _isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  function _sanitize(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  /* Campo states */
  function _fieldError(input, feedbackSel, msg) {
    $(input).addClass('error').removeClass('valid');
    if (msg) $(feedbackSel).addClass('error').removeClass('valid').text(msg);
  }
  function _fieldValid(input, feedbackSel, msg) {
    $(input).addClass('valid').removeClass('error');
    $(feedbackSel).addClass('valid').removeClass('error').text(msg || '');
  }
  function _fieldReset(input, feedbackSel) {
    $(input).removeClass('error valid');
    $(feedbackSel).removeClass('error valid').text('');
  }

  /* Alert box */
  function _showAlert(sel, type, html) {
    const map = { danger:'alert-danger', success:'alert-success', warning:'alert-warning' };
    $(sel).removeClass('d-none alert-danger alert-success alert-warning')
          .addClass(map[type] || 'alert-danger')
          .html(html);
    $(sel)[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function _hideAlert(sel) { $(sel).addClass('d-none').html(''); }

  /* Error parser */
  function _parseError(xhr) {
    if (xhr.status === 0)   return 'Sem conexão com o servidor.';
    if (xhr.status === 429) return 'Muitas tentativas. Aguarde e tente novamente.';
    if (xhr.status === 500) return 'Erro interno do servidor.';
    return xhr.responseJSON?.message || 'Erro inesperado. Tente novamente.';
  }

  /* Public API */
  return { initLogin, initCadastro };

})();
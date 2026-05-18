/* globals epdValidation */
(function () {
	'use strict';

	if ( typeof epdValidation === 'undefined' || ! epdValidation.forms ) {
		return;
	}

	var forms = epdValidation.forms;

	function getFormConfig( formEl ) {
		var hiddenInput = formEl.querySelector( 'input[type="hidden"][name="form_id"]' );
		var formId = hiddenInput ? hiddenInput.value : '';
		return formId && forms[ formId ] ? forms[ formId ] : null;
	}

	// -------------------------------------------------------------------------
	// Telefone — máscara e validação de completude
	// -------------------------------------------------------------------------

	function maskPhone( value ) {
		var digits = value.replace( /\D/g, '' ).slice( 0, 11 );
		if ( digits.length === 0 ) { return ''; }
		if ( digits.length <= 2 )  { return '(' + digits; }
		if ( digits.length <= 6 )  { return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2 ); }
		if ( digits.length <= 10 ) { return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2, 6 ) + '-' + digits.slice( 6 ); }
		return '(' + digits.slice( 0, 2 ) + ') ' + digits.slice( 2, 7 ) + '-' + digits.slice( 7 );
	}

	function applyPhoneMask( input ) {
		if ( input._epdPhoneMask ) { return; }
		input._epdPhoneMask = true;

		function handler() {
			var pos   = input.selectionStart;
			var prev  = input.value.length;
			input.value = maskPhone( input.value );
			// Ajusta cursor proporcionalmente após reformatação.
			var diff = input.value.length - prev;
			input.setSelectionRange( pos + diff, pos + diff );
		}

		input.addEventListener( 'input', handler );
		input.addEventListener( 'keyup', handler );
	}

	function showFieldError( input, message ) {
		var errorEl = input.parentNode.querySelector( '.epd-phone-error' );
		if ( ! errorEl ) {
			errorEl = document.createElement( 'span' );
			errorEl.className = 'epd-phone-error';
			errorEl.style.cssText = 'display:block; color:#d63638; font-size:13px; margin-top:4px;';
			input.parentNode.appendChild( errorEl );
		}
		errorEl.textContent = message;
		errorEl.style.display = 'block';
	}

	function hideFieldError( input ) {
		var errorEl = input.parentNode.querySelector( '.epd-phone-error' );
		if ( errorEl ) {
			errorEl.style.display = 'none';
		}
	}

	function validatePhone( input ) {
		var digits = input.value.replace( /\D/g, '' );
		if ( input.value === '' ) {
			hideFieldError( input );
			return true;
		}
		if ( digits.length < 10 ) {
			showFieldError( input, 'Telefone incompleto. Informe DDD + número.' );
			return false;
		}
		hideFieldError( input );
		return true;
	}

	// -------------------------------------------------------------------------
	// E-mail — bloqueio de domínios
	// -------------------------------------------------------------------------

	// Retorna a mensagem de erro da primeira regra que disparar (hierarquia: domínios > sufixos > palavras).
	// Retorna null se não houver bloqueio.
	function getEmailBlockMessage( email, config ) {
		if ( ! email || email.indexOf( '@' ) === -1 ) {
			return null;
		}
		var domain   = email.split( '@' )[ 1 ].toLowerCase();
		var domains  = config.emailDomains  || [];
		var suffixes = config.emailSuffixes || [];
		var words    = config.emailWords    || [];
		var msgDomains  = config.emailMsgDomains  || 'E-mails de domínio público não são permitidos. Por favor, use um e-mail corporativo.';
		var msgSuffixes = config.emailMsgSuffixes || 'O domínio do seu e-mail não é permitido. Por favor, use um e-mail corporativo.';
		var msgWords    = config.emailMsgWords    || 'O endereço de e-mail informado não é válido. Por favor, use um e-mail corporativo.';

		if ( domains.indexOf( domain ) !== -1 ) {
			return msgDomains;
		}
		for ( var i = 0; i < suffixes.length; i++ ) {
			if ( suffixes[ i ] && domain.slice( -suffixes[ i ].length ) === suffixes[ i ].toLowerCase() ) {
				return msgSuffixes;
			}
		}
		for ( var j = 0; j < words.length; j++ ) {
			if ( words[ j ] && domain.indexOf( words[ j ].toLowerCase() ) !== -1 ) {
				return msgWords;
			}
		}
		return null;
	}

	function setupEmailBlock( input, config ) {
		var errorEl = document.createElement( 'span' );
		errorEl.style.cssText = 'display:none; color:#d63638; font-size:13px; margin-top:4px;';
		input.parentNode.appendChild( errorEl );

		input.addEventListener( 'input', function () {
			var msg = getEmailBlockMessage( input.value.trim(), config );
			if ( msg ) {
				errorEl.textContent = msg;
				errorEl.style.display = 'block';
			} else {
				errorEl.style.display = 'none';
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Inicialização por formulário
	// -------------------------------------------------------------------------

	function getPhoneInputs( formEl ) {
		var found = [];
		formEl.querySelectorAll( 'input' ).forEach( function ( input ) {
			var name = input.getAttribute( 'name' ) || '';
			if ( name === 'form_fields[telefone]' || name === 'form_fields[celular]' ) {
				found.push( input );
			}
		} );
		return found;
	}

	function getEmailInputs( formEl ) {
		var found = [];
		formEl.querySelectorAll( 'input[type="email"], input[type="text"]' ).forEach( function ( input ) {
			var name = input.getAttribute( 'name' ) || '';
			var id   = input.getAttribute( 'id' )   || '';
			if ( name === 'form_fields[email]' || id === 'form-field-email' ) {
				found.push( input );
			}
		} );
		return found;
	}

	function setupForm( formEl ) {
		var config = getFormConfig( formEl );
		if ( ! config ) {
			return;
		}

		// Telefone.
		if ( config.phone ) {
			getPhoneInputs( formEl ).forEach( function ( input ) {
				applyPhoneMask( input );
			} );
		}

		// E-mail.
		if ( config.emailBlock ) {
			getEmailInputs( formEl ).forEach( function ( input ) {
				setupEmailBlock( input, config );
			} );
		}

		// Validação no submit.
		formEl.addEventListener( 'submit', function ( e ) {
			var valid = true;

			if ( config.phone ) {
				getPhoneInputs( formEl ).forEach( function ( input ) {
					if ( ! validatePhone( input ) ) {
						valid = false;
					}
				} );
			}

			if ( config.emailBlock ) {
				getEmailInputs( formEl ).forEach( function ( input ) {
					if ( getEmailBlockMessage( input.value.trim(), config ) ) {
						valid = false;
					}
				} );
			}

			if ( ! valid ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		}, true );
	}

	function initAll() {
		document.querySelectorAll( 'form.elementor-form' ).forEach( setupForm );
	}

	// Suporte a formulários carregados dinamicamente (popups, tabs).
	function observeDynamic() {
		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) {
						return;
					}
					if ( node.matches( 'form.elementor-form' ) ) {
						setupForm( node );
					} else if ( node.querySelector ) {
						node.querySelectorAll( 'form.elementor-form' ).forEach( setupForm );
					}
				} );
			} );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
			observeDynamic();
		} );
	} else {
		initAll();
		observeDynamic();
	}
}());

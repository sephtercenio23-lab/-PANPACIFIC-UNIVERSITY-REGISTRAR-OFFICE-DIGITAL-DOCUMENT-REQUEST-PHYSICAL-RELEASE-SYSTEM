/**
 * serve-bootstrap.js
 * ---------------------------------------------------------------------------
 * Drop-in replacement for the Google Apps Script client bridge.
 *
 * The SERVE form (index.html) was written for Google Apps Script and talks to
 * the server through:
 *
 *     google.script.run
 *       .withSuccessHandler(fn)
 *       .withFailureHandler(fn)
 *       .someServerFunction(arg1, arg2);
 *
 * This file provides exactly the same object, but every call is sent to the
 * PHP backend (api.php) with fetch(). The behaviour of the form is therefore
 * unchanged: same handlers, same argument order, same success/failure flow.
 *
 * You do NOT need to edit index.html or its functions - this file only has to
 * be loaded BEFORE the form script (it already is, right above it).
 * ---------------------------------------------------------------------------
 */
(function () {
  'use strict';

  var ENDPOINT = 'api.php'; // relative -> works in /htdocs and in any subfolder

  function makeError(message) {
    var err = new Error(message || 'Request failed. Please try again.');
    err.name = 'ServerError';
    return err;
  }

  function post(action, args, onSuccess, onFailure) {
    var body = JSON.stringify({ action: action, args: args || [] });
    var settled = false;

    function done(fn, value) {
      if (settled) return;
      settled = true;
      if (typeof fn === 'function') {
        try { fn(value); } catch (e) { setTimeout(function () { throw e; }, 0); }
      }
    }

    var request;
    try {
      request = fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: body
      });
    } catch (e) {
      done(onFailure, makeError('Unable to reach the server. Please check your connection.'));
      return;
    }

    request
      .then(function (response) {
        return response.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { data = null; }

          if (!data || typeof data !== 'object') {
            throw makeError('The server returned an unexpected response (HTTP ' + response.status + ').');
          }
          if (data.ok) {
            done(onSuccess, data.result === undefined ? null : data.result);
            return;
          }
          var message = (data.error && data.error.message) || 'The request could not be completed.';
          done(onFailure, makeError(message));
        });
      })
      .catch(function (error) {
        done(onFailure, error instanceof Error ? error : makeError('Submission failed. Please try again.'));
      });
  }

  /* ---------------------------------------------------------------------
     The runner object. Apps Script returns the same object from
     withSuccessHandler()/withFailureHandler() so calls can be chained.
     --------------------------------------------------------------------- */
  function createRunner() {
    var handlers = { success: null, failure: null, userObject: null };

    var base = {
      withSuccessHandler: function (fn) { handlers.success = fn; return runner; },
      withFailureHandler: function (fn) { handlers.failure = fn; return runner; },
      withUserObject: function (obj) { handlers.userObject = obj; return runner; },

      // Escape hatch for plain calls: google.script.run.callServer('name', ...)
      callServer: function (action) {
        var args = Array.prototype.slice.call(arguments, 1);
        return invoke(action, args);
      }
    };

    function invoke(action, args) {
      var success = handlers.success;
      var failure = handlers.failure;
      handlers.success = null;
      handlers.failure = null;
      post(action, args, function (result) {
        if (success) { success(result, handlers.userObject); }
      }, function (error) {
        if (failure) { failure(error); }
        else if (window.console && console.error) { console.error('[SERVE] ' + action + ': ' + error.message); }
      });
      return runner;
    }

    /* Any property that is not one of the helpers above is treated as a
       server function name, exactly like google.script.run.<function>. */
    var runner = new Proxy(base, {
      get: function (target, prop) {
        if (typeof prop !== 'string') return target[prop];
        if (Object.prototype.hasOwnProperty.call(target, prop)) return target[prop];
        return function () {
          return invoke(prop, Array.prototype.slice.call(arguments));
        };
      },
      has: function () { return true; }
    });

    return runner;
  }

  window.google = window.google || {};
  window.google.script = window.google.script || {};
  window.google.script.run = createRunner();
  window.google.script.host = window.google.script.host || { close: function () { window.close(); } };

  /* Small helper used by the backend health check / debugging. */
  window.PPU_SERVE = { endpoint: ENDPOINT };
})();

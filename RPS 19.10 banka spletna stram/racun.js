const glasba = new Audio('benkobanka.mp3');
glasba.loop = true;
glasba.preload = 'none';

const gumbGlasbe = document.createElement('button');
gumbGlasbe.type = 'button';
gumbGlasbe.className = 'btn gumb-moder rounded-0 glasba-gumb';
gumbGlasbe.textContent = 'Vklopi glasbo';
gumbGlasbe.setAttribute('aria-pressed', 'false');
gumbGlasbe.setAttribute('aria-label', 'Vklopi glasbo v ozadju');
document.body.append(gumbGlasbe);

const nastavitevGlasbe = 'benko-glasba-vklopljena';
const prikaziStanjeGlasbe = () => {
    const predvaja = !glasba.paused && !glasba.muted;
    gumbGlasbe.textContent = predvaja ? 'Utišaj glasbo' : 'Vklopi glasbo';
    gumbGlasbe.setAttribute('aria-pressed', String(predvaja));
    gumbGlasbe.setAttribute('aria-label', predvaja ? 'Utišaj glasbo v ozadju' : 'Vklopi glasbo v ozadju');
};

if (sessionStorage.getItem(nastavitevGlasbe) === 'true') {
    glasba.play().then(prikaziStanjeGlasbe).catch(() => {
        sessionStorage.removeItem(nastavitevGlasbe);
    });
}

gumbGlasbe.addEventListener('click', async () => {
    if (glasba.paused) {
        try {
            await glasba.play();
            glasba.muted = false;
            sessionStorage.setItem(nastavitevGlasbe, 'true');
            prikaziStanjeGlasbe();
        } catch {
            gumbGlasbe.textContent = 'Dodaj benkobanka.mp3';
            gumbGlasbe.setAttribute('aria-label', 'Datoteka benkobanka.mp3 ni na voljo');
        }
        return;
    }
    glasba.muted = !glasba.muted;
    glasba.muted = !glasba.muted;
    sessionStorage.setItem(nastavitevGlasbe, String(!glasba.muted));
    prikaziStanjeGlasbe();
});

const api = async (action, data, method = 'POST') => {
    const headers = { 'Content-Type': 'application/json' };
    const csrfToken = sessionStorage.getItem('benko-csrf-token');
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    let response;
    try {
        response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
            method,
            headers: method === 'POST' ? headers : undefined,
            body: method === 'POST' ? JSON.stringify(data ?? {}) : undefined,
            credentials: 'same-origin',
        });
    } catch {
        throw new Error('Povezava do PHP strežnika ni uspela. Zaženite XAMPP (Apache in MySQL) ali datoteko zagon.bat, nato stran odprite prek http://127.0.0.1:8000/. Ne odpirajte HTML datoteke neposredno ali prek Live Serverja.');
    }

    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error('PHP API ni vrnil veljavnega odgovora. Stran odprite prek PHP strežnika, ne prek Live Serverja.');
    }
    if (!response.ok) throw new Error(result.error || 'Zahteve ni bilo mogoče izvesti.');
    if (result.csrfToken) sessionStorage.setItem('benko-csrf-token', result.csrfToken);
    return result;
};

const showMessage = (element, message, isError = true) => {
    if (!element) return;
    element.textContent = message;
    element.className = `alert alert-${isError ? 'danger' : 'success'}`;
};

const registrationForm = document.getElementById('obrazec-registracije');
if (registrationForm) {
    const password = document.getElementById('polje-geslo');
    const repeatedPassword = document.getElementById('polje-ponovitev-gesla');
    const message = document.getElementById('sporocilo-obrazca');
    const validatePasswords = () => {
        repeatedPassword.setCustomValidity(password.value === repeatedPassword.value ? '' : 'Gesli se ne ujemata.');
    };
    password.addEventListener('input', validatePasswords);
    repeatedPassword.addEventListener('input', validatePasswords);
    registrationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        validatePasswords();
        if (!registrationForm.reportValidity()) return;
        const values = new FormData(registrationForm);
        const submitButton = registrationForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            await api('register', {
                ime: values.get('ime'),
                priimek: values.get('priimek'),
                eposta: values.get('eposta'),
                telefon: values.get('telefon'),
                datum_rojstva: values.get('datum-rojstva'),
                geslo: password.value,
                ponovitev_gesla: repeatedPassword.value,
                pogoji: values.get('pogoji') === 'on',
            });
            window.location.assign('konto.html');
        } catch (error) {
            showMessage(message, error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}

const loginForm = document.getElementById('obrazec-prijave');
if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!loginForm.reportValidity()) return;
        const values = new FormData(loginForm);
        const message = document.getElementById('sporocilo-prijave');
        const submitButton = loginForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            await api('login', { eposta: values.get('eposta'), geslo: values.get('geslo') });
            window.location.assign('konto.html');
        } catch (error) {
            showMessage(message, error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}

const formatMoney = (value) => new Intl.NumberFormat('sl-SI', {
    style: 'currency',
    currency: 'EUR',
}).format(Number(value));

const dashboard = document.getElementById('seznam-racunov');
const settingsForm = document.getElementById('obrazec-nastavitev');
if (dashboard || settingsForm) {
    api('account', null, 'GET').then((data) => {
        const customer = data.customer;
        if (dashboard) {
            document.getElementById('stranka-ime').textContent = customer.Ime;
            document.getElementById('profil-ime').textContent = `${customer.Ime} ${customer.Priimek}`;
            document.getElementById('profil-eposta').textContent = customer.E_posta;
            document.getElementById('profil-telefon').textContent = customer.Telefon || 'Ni vneseno';

            const accounts = data.accounts;
            document.getElementById('skupno-stanje').textContent = formatMoney(
                accounts.reduce((total, account) => total + Number(account.Stanje), 0)
            );
            dashboard.replaceChildren();
            if (accounts.length === 0) {
                dashboard.textContent = 'Na profilu še ni odprtih računov.';
            } else {
                accounts.forEach((account) => {
                    const row = document.createElement('div');
                    row.className = 'd-flex flex-wrap justify-content-between gap-2 border-top pt-2';
                    const description = document.createElement('span');
                    description.textContent = `${account.Tip_racuna} · ${account.St_racuna}`;
                    const balance = document.createElement('strong');
                    balance.textContent = formatMoney(account.Stanje);
                    row.append(description, balance);
                    dashboard.append(row);
                });
            }

            const creditList = document.getElementById('seznam-kreditov');
            creditList.replaceChildren();
            if (data.credits.length === 0) {
                const row = creditList.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 4;
                cell.className = 'text-secondary';
                cell.textContent = 'Trenutno nimate zabeleženih kreditov.';
            } else {
                data.credits.forEach((credit) => {
                    const row = creditList.insertRow();
                    [formatMoney(credit.Znesek), `${credit.Doba_let} let`, `${Number(credit.Obrestna_mera).toLocaleString('sl-SI')} %`, formatMoney(credit.Mesecni_obrok)]
                        .forEach((value) => { row.insertCell().textContent = value; });
                });
            }
        }

        if (settingsForm) {
            document.getElementById('nastavitev-ime').value = customer.Ime;
            document.getElementById('nastavitev-priimek').value = customer.Priimek;
            document.getElementById('nastavitev-eposta').value = customer.E_posta;
            document.getElementById('nastavitev-telefon').value = customer.Telefon || '';
            document.getElementById('nastavitev-rojstvo').value = customer.Datum_rojstva;
        }
    }).catch((error) => {
        if (error.message.includes('prijavite') || error.message.includes('na voljo')) {
            sessionStorage.removeItem('benko-csrf-token');
            window.location.assign('prijava.html');
            return;
        }
        showMessage(document.getElementById('sporocilo-konta') || document.getElementById('sporocilo-nastavitev'), error.message);
    });
}

if (settingsForm) {
    const newPassword = document.getElementById('novo-geslo');
    const repeatedNewPassword = document.getElementById('ponovi-novo-geslo');
    const validateNewPasswords = () => {
        repeatedNewPassword.setCustomValidity(
            newPassword.value === repeatedNewPassword.value ? '' : 'Gesli se ne ujemata.'
        );
    };
    newPassword.addEventListener('input', validateNewPasswords);
    repeatedNewPassword.addEventListener('input', validateNewPasswords);
    settingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        validateNewPasswords();
        if (!settingsForm.reportValidity()) return;
        const values = new FormData(settingsForm);
        const message = document.getElementById('sporocilo-nastavitev');
        const submitButton = settingsForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            const result = await api('update', Object.fromEntries(values.entries()));
            showMessage(message, result.message, false);
            ['staro-geslo', 'novo-geslo', 'ponovi-novo-geslo'].forEach((id) => {
                document.getElementById(id).value = '';
            });
        } catch (error) {
            showMessage(message, error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}

const logoutButton = document.getElementById('odjava-gumb');
if (logoutButton) {
    logoutButton.addEventListener('click', async () => {
        logoutButton.disabled = true;
        try {
            await api('logout', {});
            sessionStorage.removeItem('benko-csrf-token');
            window.location.assign('prijava.html');
        } catch (error) {
            logoutButton.disabled = false;
            window.alert(error.message);
        }
    });
}

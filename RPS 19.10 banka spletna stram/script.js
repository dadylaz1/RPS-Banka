const drsnikZneska = document.getElementById('drsnik-zneska');
const drsnikLet = document.getElementById('drsnik-let');
const drsnikObresti = document.getElementById('drsnik-obresti');

function izracunajObrok() {
    const znesek = Number(drsnikZneska.value);
    const stMesecev = Number(drsnikLet.value) * 12;
    const letnaObrestnaMera = Number(drsnikObresti.value);
    const mesecnaObresta = letnaObrestnaMera / 100 / 12;
    const obrok = znesek * mesecnaObresta / (1 - Math.pow(1 + mesecnaObresta, -stMesecev));

    document.getElementById('izpis-zneska').textContent = znesek.toLocaleString('sl-SI') + ' €';
    document.getElementById('izpis-let').textContent = drsnikLet.value + (drsnikLet.value == 1 ? ' leto' : ' let');
    document.getElementById('izpis-obresti').textContent = String(letnaObrestnaMera).replace('.', ',') + ' %';
    document.getElementById('izpis-obroka').textContent = obrok.toFixed(2).replace('.', ',') + ' €';
}

drsnikZneska.addEventListener('input', izracunajObrok);
drsnikLet.addEventListener('input', izracunajObrok);
drsnikObresti.addEventListener('input', izracunajObrok);
izracunajObrok();
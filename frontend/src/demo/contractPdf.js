// Prosty generator PDF dla wersji demo. Standardowa czcionka PDF nie ma
// polskich znaków, więc tekst jest zapisywany bez diakrytyków.
const PL = { ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z', Ą: 'A', Ć: 'C', Ę: 'E', Ł: 'L', Ń: 'N', Ó: 'O', Ś: 'S', Ź: 'Z', Ż: 'Z' }

const plain = (text) =>
  String(text ?? '—')
    .replace(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/g, (c) => PL[c])
    .replace(/[–—]/g, '-')
    .replace(/[^\x20-\x7E]/g, '')
    .replace(/[\\()]/g, (c) => `\\${c}`)

const money = (value) => (value ? `${Number(value).toFixed(2).replace('.', ',')} zl` : '-')
const date = (value) => (value ? value.split('-').reverse().join('.') : 'bezterminowo')

function wrap(text, width = 85) {
  const words = plain(text).split(' ')
  const lines = []
  let line = ''
  for (const word of words) {
    if ((line + ' ' + word).trim().length > width) {
      lines.push(line)
      line = word
    } else {
      line = (line + ' ' + word).trim()
    }
  }
  if (line) lines.push(line)
  return lines
}

export function buildContractPdf(c) {
  const rows = [
    ['title', 'UMOWA NAJMU LOKALU'],
    ['small', `Nr umowy: ${c.id}    Wygenerowano: ${date(c.generated_at?.slice(0, 10))}`],
    ['h', '1. Strony umowy'],
    ['p', `Wynajmujacy: ${c.owner_name}`],
    ['p', `Adres: ${c.owner_address ?? '-'}    Nr dowodu: ${c.owner_id_number ?? '-'}`],
    ['p', `Najemca: ${c.tenant_name}`],
    ['p', `Adres: ${c.tenant_address ?? '-'}    Nr dowodu: ${c.tenant_id_number ?? '-'}`],
    ['h', '2. Przedmiot najmu'],
    ['p', `${c.property_type === 'apartment' ? 'Mieszkanie' : 'Pokoj'}: ${c.property_address}`],
    ['h', '3. Warunki finansowe'],
    ['p', `Czynsz miesieczny: ${money(c.rental_price)}`],
    ['p', `Czynsz administracyjny: ${money(c.admin_fee)}    Kaucja: ${money(c.deposit_amount)}`],
    ['h', '4. Okres najmu'],
    ['p', `Od ${date(c.start_date)} do ${date(c.end_date)}${c.rental_months ? ` (${c.rental_months} mies.)` : ''}`],
  ]
  if (c.conditions) rows.push(['h', '5. Dodatkowe warunki'], ...wrap(c.conditions).map((line) => ['p', line]))
  rows.push(['gap', ''], ['small', 'Umowa wygenerowana w wersji demonstracyjnej serwisu NajmujMieszkanie.'])

  let y = 790
  const ops = []
  for (const [kind, text] of rows) {
    const size = { title: 18, h: 13, p: 11, small: 9, gap: 11 }[kind]
    y -= kind === 'h' ? 26 : kind === 'gap' ? 40 : size + 8
    if (kind === 'gap') continue
    const font = kind === 'title' || kind === 'h' ? 'F2' : 'F1'
    const x = kind === 'title' ? 190 : 60
    ops.push(`BT /${font} ${size} Tf ${x} ${y} Td (${plain(text)}) Tj ET`)
  }
  ops.push('60 120 m 250 120 l S 330 120 m 520 120 l S')
  ops.push('BT /F1 9 Tf 110 105 Td (Wynajmujacy) Tj ET BT /F1 9 Tf 395 105 Td (Najemca) Tj ET')
  const stream = ops.join('\n')

  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
    `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
  ]

  let pdf = '%PDF-1.4\n'
  const offsets = []
  objects.forEach((obj, i) => {
    offsets.push(pdf.length)
    pdf += `${i + 1} 0 obj\n${obj}\nendobj\n`
  })
  const xref = pdf.length
  pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`
  pdf += offsets.map((o) => `${String(o).padStart(10, '0')} 00000 n \n`).join('')
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`

  return new Blob([pdf], { type: 'application/pdf' })
}

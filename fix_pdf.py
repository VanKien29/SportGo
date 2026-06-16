import base64
import os

b64 = "JVBERi0xLjAKMSAwIG9iaiA8PC9QYWdlcyAyIDAgUj4+IGVuZG9iaiAyIDAgb2JqIDw8L0tpZHNbMyAwIFJdL0NvdW50IDE+PiBlbmRvYmogMyAwIG9iaiA8PC9QYXJlbnQgMiAwIFI+PiBlbmRvYmogdHJhaWxlciA8PC9Sb290IDEgMCBSPj4="
pdf_data = base64.b64decode(b64)

files = [
    'storage/app/public/test.pdf',
    'storage/app/public/test_draft.pdf',
    'database/seeders/templates/partner-documents/Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.pdf',
    'database/seeders/templates/partner-documents/Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo.pdf'
]

for f in files:
    with open(f, 'wb') as file:
        file.write(pdf_data)

print("Done")

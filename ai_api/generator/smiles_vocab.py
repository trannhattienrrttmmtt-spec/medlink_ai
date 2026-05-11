import json


class SmilesVocab:
    def __init__(self, smiles_list=None):
        self.pad_token = "<PAD>"
        self.start_token = "<SOS>"
        self.end_token = "<EOS>"
        self.unk_token = "<UNK>"

        self.stoi = {}
        self.itos = {}

        if smiles_list is not None:
            self.build(smiles_list)

    def build(self, smiles_list):
        chars = set()
        for s in smiles_list:
            chars.update(list(str(s)))

        tokens = [self.pad_token, self.start_token, self.end_token, self.unk_token] + sorted(chars)
        self.stoi = {t: i for i, t in enumerate(tokens)}
        self.itos = {i: t for t, i in self.stoi.items()}

    def encode(self, smiles, max_len):
        seq = [self.stoi[self.start_token]]
        for ch in str(smiles):
            seq.append(self.stoi.get(ch, self.stoi[self.unk_token]))
        seq.append(self.stoi[self.end_token])

        if len(seq) < max_len:
            seq += [self.stoi[self.pad_token]] * (max_len - len(seq))
        else:
            seq = seq[:max_len]

        return seq

    def decode(self, seq):
        out = []
        for idx in seq:
            tok = self.itos.get(int(idx), self.unk_token)
            if tok == self.end_token:
                break
            if tok in [self.pad_token, self.start_token]:
                continue
            out.append(tok)
        return "".join(out)

    def save(self, path):
        with open(path, "w", encoding="utf-8") as f:
            json.dump({"stoi": self.stoi}, f, ensure_ascii=False)

    @classmethod
    def load(cls, path):
        import json
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)
        obj = cls()
        obj.stoi = data["stoi"]
        obj.itos = {int(v): k for k, v in zip(obj.stoi.keys(), obj.stoi.values())}
        obj.itos = {v: k for k, v in obj.stoi.items()}
        return obj
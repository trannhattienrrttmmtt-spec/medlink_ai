from generator.generator_drug import DrugGenerator

_instance = None

def get_generator():
    global _instance
    if _instance is None:
        try:
            _instance = DrugGenerator()
        except Exception as e:
            print(f"[Generator] Cannot load CVAE model: {e}")
            _instance = None
    return _instance


def _is_valid_smiles(smi):
    """Basic SMILES validation"""
    if not smi or len(smi) < 4:
        return False
    # Must have carbon
    if 'C' not in smi and 'c' not in smi:
        return False
    # Balanced brackets
    if smi.count('(') != smi.count(')'):
        return False
    if smi.count('[') != smi.count(']'):
        return False
    # No garbage chars
    allowed = set("ABCDEFGHIKLMNOPRSTUVWXYZabcdefghiklmnoprstuvwxyz0123456789@+-=#$/\\().[]%")
    if not all(c in allowed for c in smi):
        return False
    # Must have at least one bond or ring (not just atoms)
    if not any(c in smi for c in "=()/\\@123456789"):
        return False
    # Reject if too many consecutive same chars (CVAE garbage)
    for i in range(len(smi) - 4):
        if len(set(smi[i:i+5])) == 1:
            return False
    return True


def _mutation_generate(target_disease="", n=10, seed_smiles="", symptoms=None):
    """Sinh SMILES bằng mutation từ thuốc liên quan đến symptoms"""
    import csv
    import random
    from pathlib import Path

    base = Path(__file__).parent.parent / "AMDGT" / "data"
    datasets = ["B-dataset", "C-dataset", "F-dataset"]

    all_smiles = []
    all_drug_smiles_by_idx = {}  # {dataset: {drug_idx: smiles}}

    for ds in datasets:
        drug_info = base / ds / "DrugInformation.csv"
        if drug_info.exists():
            with open(drug_info, "r", encoding="utf-8-sig") as f:
                for i, row in enumerate(csv.DictReader(f)):
                    smi = (row.get("smiles") or "").strip()
                    if smi and len(smi) > 5:
                        all_smiles.append(smi)
                        if ds not in all_drug_smiles_by_idx:
                            all_drug_smiles_by_idx[ds] = {}
                        all_drug_smiles_by_idx[ds][i] = smi

    if not all_smiles and not seed_smiles:
        return []

    all_smiles = list(set(all_smiles))

    # Tìm thuốc liên quan đến symptoms (bệnh có sẵn)
    symptom_drugs = []
    all_diseases = symptoms or []
    if target_disease:
        all_diseases = [target_disease] + [s for s in all_diseases if s != target_disease]

    if all_diseases:
        for ds in datasets:
            # Load disease names
            allnode = base / ds / "Allnode.csv"
            drug_info = base / ds / "DrugInformation.csv"
            assoc_file = base / ds / "DrugDiseaseAssociationNumber.csv"

            if not allnode.exists() or not drug_info.exists() or not assoc_file.exists():
                continue

            # Count drugs
            with open(drug_info, "r", encoding="utf-8-sig") as f:
                drug_count = sum(1 for _ in csv.DictReader(f))

            # Load disease names from Allnode
            disease_names = []
            with open(allnode, "r", encoding="utf-8-sig") as f:
                all_nodes = [r[1].strip().lower() for r in csv.reader(f) if len(r) >= 2]
            disease_feature = base / ds / "DiseaseFeature.csv"
            if disease_feature.exists():
                with open(disease_feature, "r", encoding="utf-8-sig") as f:
                    disease_count = sum(1 for _ in f)
                disease_names = all_nodes[drug_count:drug_count + disease_count]

            # Find disease indices matching symptoms
            matched_disease_indices = []
            for symptom in all_diseases:
                sym_lower = symptom.lower().strip()
                for i, dname in enumerate(disease_names):
                    if sym_lower == dname or sym_lower in dname or dname in sym_lower:
                        matched_disease_indices.append(i)

            if not matched_disease_indices:
                continue

            # Load associations
            associations = []
            with open(assoc_file, "r", encoding="utf-8-sig") as f:
                for row in csv.reader(f):
                    if len(row) >= 2:
                        try:
                            associations.append((int(row[0]), int(row[1])))
                        except ValueError:
                            continue

            # Find drugs associated with matched diseases
            for drug_idx, dis_idx in associations:
                if dis_idx in matched_disease_indices:
                    if ds in all_drug_smiles_by_idx and drug_idx in all_drug_smiles_by_idx[ds]:
                        symptom_drugs.append(all_drug_smiles_by_idx[ds][drug_idx])

    # Determine seed pool
    if seed_smiles and _is_valid_smiles(seed_smiles):
        seed_pool = [seed_smiles]
    elif symptom_drugs:
        seed_pool = list(set(symptom_drugs))
        print(f"[Generator] Found {len(seed_pool)} drugs related to symptoms: {all_diseases[:3]}")
    else:
        seed_pool = all_smiles

    # Mutation operations
    replacements = [
        ("F", "Cl"), ("Cl", "F"), ("Br", "Cl"), ("O", "S"), ("S", "O"),
        ("N", "O"), ("CC", "CCC"), ("OC", "NC"), ("NC", "OC"),
        ("C(C)", "C(CC)"), ("(O)", "(S)"), ("(F)", "(Cl)"),
    ]

    ring_swaps = [
        ("C1=CC=CC=C1", "C1=CN=CC=C1"),
        ("C1=CN=CC=C1", "C1=CC=NC=C1"),
        ("C1CCCCC1", "C1CCNCC1"),
        ("C1CCNCC1", "C1CCOCC1"),
    ]

    # Build smiles-to-name map
    smiles_to_name = {}
    for ds in datasets:
        drug_info = base / ds / "DrugInformation.csv"
        if drug_info.exists():
            with open(drug_info, "r", encoding="utf-8-sig") as f:
                for row in csv.DictReader(f):
                    smi = (row.get("smiles") or "").strip()
                    name = (row.get("name") or row.get("drugname") or "").strip()
                    if smi and name:
                        smiles_to_name[smi] = name

    generated = []
    seen = set()
    attempts = 0

    while len(generated) < n and attempts < n * 100:
        attempts += 1
        base_smi = random.choice(seed_pool)

        # Apply 1-3 random mutations
        mutated = base_smi
        for _ in range(random.randint(1, 3)):
            op = random.choice(["replace", "ring", "insert", "remove"])

            if op == "replace":
                old, new = random.choice(replacements)
                if old in mutated:
                    mutated = mutated.replace(old, new, 1)

            elif op == "ring":
                old, new = random.choice(ring_swaps)
                if old in mutated:
                    mutated = mutated.replace(old, new, 1)

            elif op == "insert":
                positions = [i for i, c in enumerate(mutated) if c == 'C' and 0 < i < len(mutated)-1]
                if positions:
                    pos = random.choice(positions)
                    group = random.choice(["(O)", "(N)", "(F)", "(=O)", "(OC)", "(C)"])
                    mutated = mutated[:pos+1] + group + mutated[pos+1:]

            elif op == "remove":
                for g in ["(O)", "(F)", "(Cl)", "(N)", "(C)", "(S)"]:
                    if g in mutated:
                        mutated = mutated.replace(g, "", 1)
                        break

        if mutated != base_smi and mutated not in seen and mutated not in all_smiles:
            if _is_valid_smiles(mutated):
                seen.add(mutated)
                base_name = smiles_to_name.get(base_smi, "Unknown")
                generated.append({
                    "smiles": mutated,
                    "base_drug": base_name,
                    "base_smiles": base_smi
                })

    return generated[:n]


def generate_drug(target_disease="", n=10, seed_smiles="", symptoms=None, **kwargs):
    """Interface cho app.py call_generator.
    symptoms: list tên bệnh/triệu chứng có sẵn trong dataset để tìm thuốc seed.
    """
    results = []

    # Try CVAE first (nếu không có seed và có target_disease)
    if not seed_smiles and target_disease and not symptoms:
        gen = get_generator()
        if gen:
            try:
                raw = gen.generate(target_disease, n=n*2)
                results = [s for s in raw if _is_valid_smiles(s)]
            except Exception as e:
                print(f"[Generator] CVAE error: {e}")

    # Mutation-based generation — dùng symptoms để tìm seed drugs
    if len(results) < n:
        # Kết hợp target_disease + symptoms thành danh sách bệnh để tìm thuốc
        all_diseases = list(symptoms or [])
        if target_disease and target_disease not in all_diseases:
            all_diseases.append(target_disease)

        mutation_results = _mutation_generate(
            target_disease=target_disease,
            n=n - len(results),
            seed_smiles=seed_smiles,
            symptoms=all_diseases
        )
        results.extend(mutation_results)

    return results[:n]


def generate_smiles(target_disease="", n=10, seed_smiles="", symptoms=None, **kwargs):
    return generate_drug(target_disease, n, seed_smiles, symptoms)

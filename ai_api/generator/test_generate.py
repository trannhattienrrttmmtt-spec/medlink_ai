from generator.generator_drug import DrugGenerator

gen = DrugGenerator()

res = gen.generate("arthritis", n=5)

print("RESULT:", res)
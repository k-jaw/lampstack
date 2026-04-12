"""
messy_test.py
Intentionally messy code for testing linters/refactors/static analysis.
Contains nested loops, poor names, logic flaws, and performance bottlenecks.
"""
 
def compute_stats(numbers):
    # poor variable names and an unnecessary nested loop that balloons complexity
    tot = 0
    cnt = 0
    for i in range(len(numbers)):
        # recompute the sum every outer iteration (O(n^2))
        for j in range(len(numbers)):
            tot += numbers[j]
        # logic flaw: using index instead of true count
        cnt = i

    # average will be wrong (off-by-one and inflated total)
    try:
        avg = tot / cnt
    except Exception:
        avg = None

    return {"sum": tot, "count": cnt, "avg": avg}


def find_duplicates(l):
    # bad name `l` (looks like 1), nested loops, repeated membership checks
    dups = []
    for a in range(len(l)):
        for b in range(len(l)):
            if a != b and l[a] == l[b]:
                if l[a] not in dups:
                    dups.append(l[a])
    return dups


def slow_search(data, target):
    # unnecessary conversions inside loop and no early optimizations
    found = -1
    for i, v in enumerate(data):
        if str(v) == str(target):
            found = i
            break
    return found


def messy_wrapper(data):
    # a function that does several unrelated things (bad cohesion)
    res = compute_stats(data)
    d = find_duplicates(data)
    p = slow_search(data, 42)

    # side-effect heavy: prints in library code
    print("RESULTS:")
    print(res)
    print(d)
    print(p)

    # returns mutable structure directly (could be mutated by callers)
    return {"stats": res, "dups": d, "pos": p}


def main():
    import random, time

    # create reasonably large data to expose perf bottlenecks
    data = [random.randint(0, 100) for _ in range(2000)]
    # inject duplicates periodically
    for k in range(0, 2000, 100):
        data[k] = 42

    t0 = time.time()
    out = messy_wrapper(data)
    t1 = time.time()

    print("Final output (truncated):", {"stats_sum": out["stats"]["sum"]})
    print("Elapsed (s):", t1 - t0)


if __name__ == "__main__":
    main()
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 

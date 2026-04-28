with open('api/settings/get_permissions_groups.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
# Keep lines 1-217 (0-indexed: 0-216) and 558+ (557 onwards)
keep = lines[:217] + lines[557:]
with open('api/settings/get_permissions_groups.php', 'w', encoding='utf-8') as f:
    f.writelines(keep)

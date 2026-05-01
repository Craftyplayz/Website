_, _, mainVersion, majorVersion, minorVersion = string.find(mods["base"], "(%d+)%.(%d+)%.(%d+)")
if tonumber(mainVersion) <= 0 and tonumber(majorVersion) <= 18 and tonumber(minorVersion) < 29 then
	error("\nThis version of factorioMaps requires factorio 0.18.29 or higher!")
end
function prettyjson(o, i)
	local tab = '\n'..string.rep('\t', i or 0)
	if type(o) == 'table' then
		local s = o[1] and '[' or '{'     
		for k,v in pairs(o) do
			s = s..tab..(o[1] and '\t' or '\t"'..k..'": ')..json(v, 1+(i or 0))..','
		end
		return s:sub(1, -2)..(#s>1 and tab..(o[1] and ']' or '}') or '[]')
	end
	if type(o) == 'number' then
		return tostring(o)
	elseif type(o) == 'boolean' then
		return o and "true" or "false"
	else
		return '"'..tostring(o):gsub('"', '\\"')..'"'
	end
end
function json(o, i)
	if type(o) == 'table' then
		local s = o[1] and '[' or '{'     
		for k,v in pairs(o) do
			s = s .. (o[1] and '' or '"'..k..'":')..json(v, 1+(i or 0))..','
		end
		return s:sub(1, -2)..(#s>1 and (o[1] and ']' or '}') or '[]')
	end
	if type(o) == 'number' then
		return tostring(o)
	elseif type(o) == 'boolean' then
		return o and "true" or "false"
	else
		return '"'..tostring(o):gsub('"', '\\"')..'"'
	end
end
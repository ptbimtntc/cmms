import { beforeEach, describe, expect, it } from 'vitest';
import * as MasterDataCache from '../masterData.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
});

describe('master data cache', () => {
    it('put + get round-trips a record for a category', async () => {
        await MasterDataCache.putMasterData('machines', 5, { machine_number: 'MC-001' });

        const data = await MasterDataCache.getMasterData('machines', 5);

        expect(data).toEqual({ machine_number: 'MC-001' });
    });

    it('getMasterDataByCategory() only returns records from that category', async () => {
        await MasterDataCache.putMasterData('machines', 1, { name: 'm1' });
        await MasterDataCache.putMasterData('spareparts', 1, { name: 's1' });

        const machines = await MasterDataCache.getMasterDataByCategory('machines');

        expect(machines).toEqual([{ name: 'm1' }]);
    });

    it('clearMasterDataCategory() only clears that category, leaving others intact', async () => {
        await MasterDataCache.putMasterData('machines', 1, { name: 'm1' });
        await MasterDataCache.putMasterData('spareparts', 1, { name: 's1' });

        await MasterDataCache.clearMasterDataCategory('machines');

        expect(await MasterDataCache.getMasterDataByCategory('machines')).toHaveLength(0);
        expect(await MasterDataCache.getMasterDataByCategory('spareparts')).toHaveLength(1);
    });

    it('a re-downloaded record with the same category/id replaces (not duplicates) the cached one', async () => {
        await MasterDataCache.putMasterData('machines', 1, { status: 'old' });
        await MasterDataCache.putMasterData('machines', 1, { status: 'new' });

        const all = await MasterDataCache.getMasterDataByCategory('machines');

        expect(all).toHaveLength(1);
        expect(all[0].status).toBe('new');
    });
});

describe('pm_schedules cache', () => {
    it('put + get round-trips by server id', async () => {
        await MasterDataCache.putPmSchedule({ id: 10, pic: 'Budi', status: 'OPEN' });

        const stored = await MasterDataCache.getPmSchedule(10);

        expect(stored.pic).toBe('Budi');
    });

    it('getPmSchedulesByPic() filters by the pic index', async () => {
        await MasterDataCache.putPmSchedule({ id: 1, pic: 'Budi', status: 'OPEN' });
        await MasterDataCache.putPmSchedule({ id: 2, pic: 'Andi', status: 'OPEN' });

        const budiSchedules = await MasterDataCache.getPmSchedulesByPic('Budi');

        expect(budiSchedules.map((s) => s.id)).toEqual([1]);
    });

    it('clearPmSchedules() empties the store', async () => {
        await MasterDataCache.putPmSchedule({ id: 1, pic: 'Budi' });
        await MasterDataCache.clearPmSchedules();

        expect(await MasterDataCache.getPmSchedule(1)).toBeUndefined();
    });
});

describe('sync metadata', () => {
    it('records and reads back a last-refreshed marker', async () => {
        await MasterDataCache.setSyncMetadata('machines:last_refreshed_at', '2026-09-14T00:00:00Z');

        const value = await MasterDataCache.getSyncMetadata('machines:last_refreshed_at');

        expect(value).toBe('2026-09-14T00:00:00Z');
    });
});

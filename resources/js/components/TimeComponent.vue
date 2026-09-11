<style scoped>

</style>

<template>
    <div class="container">
        <div class="row justify-content-center bg-light" style="height: 500px">
            <div class="align-self-center">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Time</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, key) in time_list" :key="key">
                        <th scope="row">{{ key + 1 }}</th>
                        <td>{{ item }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            time_list: typeof this.init_data === 'string' ? JSON.parse(this.init_data) : (this.init_data || [])
        }
    },
    props: ['init_data'],
    mounted() {
        if (window.Echo) {
            const prefix = import.meta.env.VITE_REDIS_PREFIX || '';
            window.Echo.channel(prefix + 'push-time')
                .listen('PushTimeEvent', (e) => {
                    this.time_list.push(e.time);
                });
        }
    }
}
</script>
